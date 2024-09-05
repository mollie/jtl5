<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie;

use JTL\Events\Dispatcher;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use Plugin\ws5_mollie\lib\Hook\ApplePay;
use Plugin\ws5_mollie\lib\Hook\Checkbox;
use Plugin\ws5_mollie\lib\Hook\IncompletePaymentHandler;
use Plugin\ws5_mollie\lib\Hook\Queue;
use Plugin\ws5_mollie\lib\PluginHelper;

require_once __DIR__ . '/vendor/autoload.php';

class Bootstrap extends \WS\JTL5\V1_0_16\Bootstrap
{
    /**
     * @param Dispatcher $dispatcher
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->listen(HOOK_SMARTY_OUTPUTFILTER, [ApplePay::class, 'execute']);

        $this->listen(HOOK_BESTELLVORGANG_PAGE, [IncompletePaymentHandler::class, 'checkForIncompletePayment']);

        $this->listen(HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB, [Queue::class, 'bestellungInDB']);

        $this->listen(HOOK_INDEX_NAVI_HEAD_POSTGET, [Queue::class, 'headPostGet']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [Queue::class, 'xmlBestellStatus']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, [Queue::class, 'xmlBearbeiteStorno']);
        
        if (PluginHelper::getSetting('useCustomerAPI') === 'C') {
            $this->listen(HOOK_CHECKBOX_CLASS_GETCHECKBOXFRONTEND, [Checkbox::class, 'execute']);
        }
    }

    /**
     * @param $oldVersion
     * @param $newVersion
     * @return void
     */
    public function updated($oldVersion, $newVersion): void
    {
        parent::updated($oldVersion, $newVersion);

        if (PluginHelper::isShopVersionEqualOrGreaterThan('5.3.0')) {
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_kunde');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_orders');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_queue');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_shipments');  // TODO: remove this code when min. shop version is 5.3
        }
    }
}
