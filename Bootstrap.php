<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie;

use JTL\Checkout\Bestellung;
use JTL\Events\Dispatcher;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Filter\Metadata;
use JTL\Router\Router;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\CleanupCronJob;
use Plugin\ws5_mollie\lib\Hook\ApplePay;
use Plugin\ws5_mollie\lib\Hook\Checkbox;
use Plugin\ws5_mollie\lib\Hook\IncompletePaymentHandler;
use Plugin\ws5_mollie\lib\Hook\Queue;
use Plugin\ws5_mollie\lib\Hook\FrontendHook;
use Plugin\ws5_mollie\lib\PluginHelper;
use JTL\Events\Event;
use Psr\Http\Message\ServerRequestInterface;

require_once __DIR__ . '/vendor/autoload.php';

class Bootstrap extends \WS\JTL5\V2_1_4\Bootstrap
{
    private const CRON_TYPE = 'cronjob_mollie_cleanup';

    /**
     * @param Dispatcher $dispatcher
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->listen(Event::GET_AVAILABLE_CRONJOBS, [$this, 'availableCronjobType']);
        $dispatcher->listen(Event::MAP_CRONJOB_TYPE, static function (array &$args) {
            if ($args['type'] === self::CRON_TYPE) {
                $args['mapping'] = CleanupCronJob::class;
            }
        });


        $this->listen(HOOK_SMARTY_OUTPUTFILTER, [ApplePay::class, 'execute']);
        $this->listen(HOOK_SMARTY_OUTPUTFILTER, [FrontendHook::class, 'execute']);

        $this->listen(HOOK_BESTELLVORGANG_PAGE, [IncompletePaymentHandler::class, 'checkForIncompletePayment']);

        $this->listen(HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB, [Queue::class, 'bestellungInDB']);

        // Logging
        $this->listen(HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, function($args_arr) {
            if (
                PluginHelper::getSetting('debugMode')
                && array_key_exists('oBestellung', $args_arr)
                && AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kZahlungsart, true)
            ) {
                /**
                 * @var Bestellung  $oBestellung
                 */
                $oBestellung = $args_arr['oBestellung'];
                $paymentSession = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM tzahlungsession WHERE kBestellung = 0 AND nBezahlt = 0 AND cSID = :id ',
                    [
                        ':id' => $oBestellung->cSession ?? ''
                    ], 1);
                $order = (object)[
                    'kBestellung' => $oBestellung->kBestellung ?? '',
                    'cBestellNr' =>$oBestellung->cBestellNr ?? '',
                    'kZahlungsart' => $oBestellung->kZahlungsart  ?? '',
                    'cZahlungsartName' => $oBestellung->cZahlungsartName  ?? '',
                    'cZahlungsID' => $paymentSession->cZahlungsID  ? $paymentSession : ''
                ];

                PluginHelper::getLogger()->debugWithStacktrace("Mollie: Bestellung wurde in DB gespeichert | " . json_encode($order, JSON_PRETTY_PRINT));
            }
        });

        $this->listen(HOOK_INDEX_NAVI_HEAD_POSTGET, [Queue::class, 'headPostGet']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [Queue::class, 'xmlBestellStatus']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, [Queue::class, 'xmlBearbeiteStorno']);
        
        if (PluginHelper::getSetting('useCustomerAPI') === 'C') {
            $this->listen(HOOK_CHECKBOX_CLASS_GETCHECKBOXFRONTEND, [Checkbox::class, 'execute']);
        }

        // Add routes for processPaymentPage and checkPaymentStatus()-Webhook
        $this->listen(HOOK_ROUTER_PRE_DISPATCH, function ($args) {
            /** @var Router $router */
            $router = $args['router'];
            // This Route is called, when customer got redirected from mollie but webhook was not called before and order is no finalized yet
            $router->addRoute('/' . self::getPlugin()->getPluginID() . '/processPayment', function (ServerRequestInterface $request, array $args, JTLSmarty $smarty) {
                // Safety first in bootstrap
                ifndef('LINNKTYPE_BESTELLABSCHLUSS', 33);
                ifndef('LINKTYP_BESTELLSTATUS', 38);
                $linktype_ordercompleted = (int) LINKTYP_BESTELLABSCHLUSS;
                $linktype_orderstatus = (int) LINKTYP_BESTELLSTATUS;

                // Get correct redirect url from shop settings: Bestellstatus/Bestellabschluss
                if (\JTL\Shopsetting::getInstance()->getValue(CONF_KAUFABWICKLUNG, 'bestellabschluss_abschlussseite') === 'A') {
                    $redirectURL = Shop::Container()->getLinkService()->getSpecialPage($linktype_ordercompleted)->getURL();
                } else {
                    $redirectURL = Shop::Container()->getLinkService()->getSpecialPage($linktype_orderstatus)->getURL();
                }
                // Get meta data for processPaymentPage
                $maxLength       = (int)Shop::getSettingValue(\CONF_METAANGABEN, 'global_meta_maxlaenge_title');
                $globalMetaData  = Metadata::getGlobalMetaData()[Shop::getLanguageID()] ?? null;
                $metaTitle       = Metadata::prepareMeta($globalMetaData->Title ?? '', null, $maxLength);
                $errorUrl        = Shop::Container()->getLinkService()->getSpecialPage(LINKTYP_BESTELLVORGANG)->getURL();
                // Assign necessary data and load template
                return $smarty->assign('shopURL', Shop::getURL())
                    ->assign('paymentProcessPendingHeading', PluginHelper::getSprachvariableByName('paymentProcessPendingHeading'))
                    ->assign('paymentProcessPendingText', PluginHelper::getSprachvariableByName('paymentProcessPendingText'))
                    ->assign('paymentProcessFinishedText', PluginHelper::getSprachvariableByName('paymentProcessFinishedText'))
                    ->assign('errorURL', $errorUrl)
                    ->assign('redirectURL', $redirectURL)
                    ->assign('templateDir', $smarty->getTemplateUrlPath())
                    ->assign('meta_title', $metaTitle)
                    ->getResponse(__DIR__ . '/frontend/template/processPayment.tpl');
            });

            // This Route is called from processPaymentPage to check payment status while customer is waiting
            $router->addRoute('/' . self::getPlugin()->getPluginID() . '/checkPaymentStatus',  [Queue::class, 'checkPaymentStatus']);
        });


        // Add route for async queue
        if (PluginHelper::getSetting('queue') === 'async') {
            $this->listen(HOOK_ROUTER_PRE_DISPATCH, function ($args) {
                /** @var Router $router */
                $router = $args['router'];
                $router->addRoute('/' . self::getPlugin()->getPluginID() . '/queue', [\Plugin\ws5_mollie\lib\Queue::class, 'runAsynchronous'], null, ['POST']);
            });
        }

        // Add route for applePay check
        $this->listen(HOOK_ROUTER_PRE_DISPATCH, function($args) {
            /** @var Router $router */
            $router = $args['router'];
            $router->addRoute('/plugins/' . self::getPlugin()->getPluginID() . '/applepay', [ApplePay::class, 'check'], 'mollieApplePayCheck', ['POST']);
        });
    }

    /**
     * @return void
     */
    private function addCleanupCron(): void
    {
        $isInstalled = $this->getDB()->executeQueryPrepared('SELECT * FROM tcron WHERE name = :name AND jobType = :jobType',
                [
                    ':name' => 'Mollie Queue Cleanup',
                    ':jobType' => self::CRON_TYPE
                ],
                3) > 0;

        if (!$isInstalled) {
            $job            = new \stdClass();
            $job->name      = 'Mollie Queue Cleanup';
            $job->jobType   = self::CRON_TYPE;
            $job->frequency = 1;
            $job->startDate = 'NOW()';
            $job->startTime = '06:00:00';
            $this->getDB()->insert('tcron', $job);
        }
    }

    /**
     * @param array $args
     * @return void
     */
    public function availableCronjobType(array &$args): void
    {
        if (!\in_array(self::CRON_TYPE, $args['jobs'], true)) {
            $args['jobs'][] = self::CRON_TYPE;
        }
    }


    /**
     * @return void
     */
    public function installed(): void
    {
        parent::installed();
        $this->addCleanupCron();
    }

    /**
     * @param bool $deleteData
     * @return void
     */
    public function uninstalled(bool $deleteData = true): void
    {
        parent::uninstalled($deleteData);
        $this->getDB()->delete('tcron', ['name', 'jobType'], ['Mollie Queue Cleanup', self::CRON_TYPE]);
    }

    /**
     * @param $oldVersion
     * @param $newVersion
     * @return void
     */
    public function updated($oldVersion, $newVersion): void
    {
        parent::updated($oldVersion, $newVersion);

        if ($newVersion >= "1.9.0") {
            $this->addCleanupCron();
        }

        if (PluginHelper::isShopVersionEqualOrGreaterThan('5.3.0')) {
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_kunde');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_orders');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_queue');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_shipments');  // TODO: remove this code when min. shop version is 5.3
        }
    }
}
