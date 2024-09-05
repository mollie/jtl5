<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Order;

use JsonSerializable;
use JTL\Catalog\Currency;
use JTL\Shop;
use Plugin\ws5_mollie\lib\PluginHelper;
use WS\JTL5\V1_0_16\Traits\Jsonable;

class Amount implements JsonSerializable
{
    use Jsonable;

    public $value;
    public $currency;

    /**
     * Amount constructor.
     * @param $value
     * @param null|Currency $currency
     * @param bool $useRounding (is it total SUM => true [5 Rappen Rounding])
     * @todo: prüfe mit Shop4
     */
    public function __construct($value, Currency $currency = null, bool $useRounding = false)
    {
        if (!$currency) {
            $currency = self::fallbackCurrency();
        }
        $this->value    = number_format(round($useRounding ? self::round($value) : $value, 2), 2, '.', '');
        $this->currency = $currency->getCode();
    }

    /**
     * @return Currency
     */
    public static function fallbackCurrency(): Currency
    {
        $curr = $_SESSION['Waehrung'] ?? PluginHelper::getDB()->select('twaehrung', 'cStandard', 'Y');

        return new Currency($curr->kWaehrung);
    }

    /**
     * @param float $gesamtsumme
     * @return float
     */
    public static function round(float $gesamtsumme): float
    {
        $conf = Shop::getSettings([CONF_KAUFABWICKLUNG]);
        if (
            isset($conf['kaufabwicklung']['bestellabschluss_runden5'])
            && ($conf['kaufabwicklung']['bestellabschluss_runden5'] === 1)
        ) {
            // simplification. see https://de.wikipedia.org/wiki/Rundung#Rappenrundung
            $gesamtsumme = round($gesamtsumme * 20.0) / 20.0;
        }

        return $gesamtsumme;
    }
}
