<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Controller;

use Exception;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Currency;
use JTL\Catalog\Hersteller;
use JTL\Catalog\Product\Artikel;
use JTL\Customer\CustomerGroup;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Helpers\Category;
use JTL\Language\LanguageHelper;
use JTL\Language\LanguageModel;
use JTL\Plugin\Helper;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Response;
use RuntimeException;
use stdClass;

class HelperController extends AbstractController
{
    /**
     * @return Response
     */
    public static function info(): Response
    {
        return new Response([
            'test'                => true,
            'url'                 => Shop::getURL(),
            'version'             => APPLICATION_VERSION,
            'php'                 => PHP_VERSION,
            'os'                  => PHP_OS,
            'db'                  => Shop::Container()->getDB()->getServerInfo(),
            'pluginID'            => Helper::getIDByPluginID('ws5_mollie'),
            'errorReporting'      => error_reporting(),
            'adminErrorReporting' => ADMIN_LOG_LEVEL,
            'maintenanceMode'     => Shop::getSettingValue(CONF_GLOBAL, 'wartungsmodus_aktiviert') === 'Y',
            'defaults'            => [
                'kSprache'      => LanguageHelper::getDefaultLanguage(true)->getId(),
                'kWaehrung'     => (new Currency())->getDefault()->getID(),
                'kKundengruppe' => CustomerGroup::getDefaultGroupID()
            ]
        ]);
    }

    /**
     * Data properties:
     * - id?: kSprache
     *
     * @param $data stdClass
     * @throws Exception
     * @return Response
     */
    public static function language(stdClass $data): Response
    {
        $fill = function (LanguageModel $lang): stdClass {
            return (object)[
                'id'              => $lang->getId(),
                'code'            => $lang->getCode(),
                'default'         => $lang->isDefault(),
                'name'            => $lang->getLocalizedName(),
                'iso'             => $lang->getIso(),
                'iso639'          => $lang->getIso639(),
                'displayLanguage' => $lang->getDisplayLanguage(),
                'shopDefault'     => $lang->getShopDefault()
            ];
        };

        $response = [];
        if ($data->id ?? false) {
            $response = $fill(LanguageHelper::getInstance()->getLanguageByID((int)$data->id));
        } else {
            foreach (LanguageHelper::getInstance()->gibInstallierteSprachen() as $language) {
                $response[$language->getId()] = $fill($language);
            }
        }

        return new Response($response);
    }

    /**
     * Data properties:
     * - id?: kWaehrung
     *
     * @param stdClass $data
     * @return Response
     */
    public static function currency(stdClass $data): Response
    {
        $fill = function (Currency $oCurrency): stdClass {
            return (object)[
                'id'                         => $oCurrency->getID(),
                'code'                       => $oCurrency->getCode(),
                'conversionFactor'           => $oCurrency->getConversionFactor(),
                'decimalSeparator'           => $oCurrency->getDecimalSeparator(),
                'default'                    => $oCurrency->isDefault(),
                'htmlEntity'                 => $oCurrency->getHtmlEntity(),
                'name'                       => $oCurrency->getName(),
                'forcePlacementBeforeNumber' => $oCurrency->getForcePlacementBeforeNumber(),
                'thousandsSeparator'         => $oCurrency->getThousandsSeparator(),
            ];
        };

        $response = [];
        if ($data->id ?? false) {
            $response = $fill(new Currency($data->id));
        } else {
            $allCurrencies = Shop::Container()->getDB()->selectAll('twaehrung', [], [], 'kWaehrung');
            foreach ($allCurrencies as $currency) {
                $oCurrency                     = new Currency((int)$currency->kWaehrung);
                $response[$oCurrency->getID()] = $fill($oCurrency);
            }
        }

        return new Response($response);
    }

    /**
     * Data properties:
     * - customerGroupID?: kKundengruppe
     * - langID?: kSprache
     * - noCache?: bool
     * - options?: string(detail/export) | stdClass
     * - id: kArtikel || no: cArtNr
     *
     * @param stdClass $data
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @return Response
     */
    public static function product(stdClass $data): Response
    {
        $fill = function (int $kArtikel, $options = null): Artikel {
            $product = new Artikel();
            $product->fuelleArtikel($kArtikel, $options, $data->customerGroupID ?? 0, $data->langID ?? 0, $data->noCache ?? false);
            if (!$product->getID()) {
                $product = null;
            }

            return $product;
        };

        $product = null;
        $options = null;
        if (isset($data->options)) {
            if (is_string($data->options)) {
                switch ($data->options) {
                    case 'detail':
                        $options = Artikel::getDetailOptions();

                        break;
                    case 'export':
                        $options = Artikel::getExportOptions();

                        break;
                    default:
                        $options = null;
                }
            } elseif (is_object($data->options)) {
                $options = $data->options;
            }
        }
        if ($kArtikel = $data->id ?? false) {
            $product = $fill($kArtikel, $options);
        }

        if (!$product && ($cArtNr = $data->no ?? false)) {
            $kArtikel = Shop::Container()->getDB()->selectSingleRow('tartikel', 'cArtNr', $cArtNr, null, null, null, null, false, 'kArtikel')->kArtikel;
            $product  = $fill($kArtikel, $options);
        }

        if (!$product) {
            throw new RuntimeException('Product not found', 404);
        }

        return new Response($product);
    }

    /**
     * Data properties:
     * - id?: kKategorie
     * - parent?: kKategorie
     * - customerGroupID?: kKundengruppe
     * - langID?: kSprache
     * - noCache?: bool
     *
     * @param stdClass $data
     * @return Response
     */
    public static function category(stdClass $data): Response
    {
        $fill = /**
         * @return \JTL\Catalog\Category\Kategorie[]
         *
         * @psalm-return array<int, \JTL\Catalog\Category\Kategorie>
         */
        function (array $categories): array {
            $result = [];
            foreach ($categories as $category) {
                $oCategory                   = new Kategorie((int)$category->kKategorie, $data->langID ?? 0, $data->customerGroupID ?? 0, $data->noCacge ?? false);
                $result[$oCategory->getID()] = $oCategory;
            }

            return $result;
        };

        if (($data->id ?? false) && Category::categoryExists((int)$data->id)) {
            $response = new Kategorie((int)$data->id, $data->langID ?? 0, $data->customerGroupID ?? 0, $data->noCache ?? false);
        } elseif ($data->parent ?? false) {
            $categories = Shop::Container()->getDB()->selectAll('tkategorie', 'kOberKategorie', (int)$data->parent, 'kKategorie');
            $response   = $fill($categories);
        } else {
            $categories = Shop::Container()->getDB()->selectAll('tkategorie', 'kOberKategorie', 0, 'kKategorie');
            $response   = $fill($categories);
        }

        return new Response($response);
    }

    /**
     * Data properties:
     * - section: kEinstellungSektion
     * - key?: cName (teinstellungen / ttemplateeinstellungen)
     *
     * @param stdClass $data
     * @return Response
     * @see \CONF_GLOBAL, ...: includes/defines_inc.php
     * @see \CONF_TEMPLATE: Template: Section 11
     *
     */
    public static function config(stdClass $data): Response
    {
        $response = null;
        if (($data->section ?? false) && ($data->key ?? false)) {
            $response = [$data->key => Shop::getSettingValue((int)$data->section, $data->key)];
        } elseif ($data->section ?? false) {
            $response = Shop::getSettings((int)$data->section);
        }

        return new Response($response);
    }

    /**
     * Data properties:
     * - id: kHersteller
     * - langID?: kSprache
     * - noCache?: bool
     *
     * @param stdClass $data
     * @throws RuntimeException
     * @return Response
     */
    public static function manufacturer(stdClass $data): Response
    {
        $response = null;
        if ($data->id ?? false) {
            $response = new Hersteller((int)$data->id, $data->langID ?? 0, $data->noCache ?? false);
            if (!$response->getID()) {
                throw new RuntimeException('Manufacturer not found', 404);
            }
        } else {
            $hersteller_arr = Shop::Container()->getDB()->selectAll('thersteller', [], []);
            $response       = [];
            foreach ($hersteller_arr as $hersteller) {
                $response[$hersteller->kHersteller] = new Hersteller((int)$hersteller->kHersteller, $data->langID ?? 0, $data->noCache ?? false);
            }
        }

        return new Response($response);
    }

    /**
     * Data properties:
     * - query: string, SQL Query
     * - params?: array, Parameters to bind
     *
     * @param stdClass $data
     * @return Response
     */
    public static function selectOne(stdClass $data): Response
    {
        $response = null;
        if ($data->query ?? false) {
            $response = Shop::Container()->getDB()->executeQueryPrepared($data->query, (array)($data->params ?? []), 1);
        }
        if ($response === false) {
            throw new \RuntimeException('DB Error');
        }

        return new Response($response);
    }

    /**
     * Data properties:
     * - query: string, SQL Query
     * - params?: array, Parameters to bind
     *
     * @param stdClass $data
     * @return Response
     */
    public static function selectAll(stdClass $data): Response
    {
        $response = null;
        if ($data->query ?? false) {
            if (isset($data->query) && property_exists($data->params, ':limit') && property_exists($data->params, ':offset') && strpos($data->query, 'LIMIT') === false) {
                $query    = rtrim($data->query, ';') . ' LIMIT :offset, :limit;';
                $response = (object)[
                    'items'    => Shop::Container()->getDB()->executeQueryPrepared($query, (array)($data->params ?? []), 2),
                    'maxItems' => Shop::Container()->getDB()->executeQueryPrepared($data->query, (array)($data->params ?? []), 3),
                ];
            } else {
                $response = Shop::Container()->getDB()->executeQueryPrepared($data->query, (array)($data->params ?? []), 2);
            }
        }
        if (!is_array($response) && ($error = Shop::Container()->getDB()->getErrorMessage())) {
            throw new \RuntimeException('DB Error: ' . $error);
        }

        return new Response($response);
    }
}
