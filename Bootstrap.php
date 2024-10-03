<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie;

use JTL\Events\Dispatcher;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Router\Router;
use Plugin\ws5_mollie\lib\CleanupCronJob;
use Plugin\ws5_mollie\lib\Hook\ApplePay;
use Plugin\ws5_mollie\lib\Hook\Checkbox;
use Plugin\ws5_mollie\lib\Hook\IncompletePaymentHandler;
use Plugin\ws5_mollie\lib\Hook\Queue;
use Plugin\ws5_mollie\lib\Hook\FrontendHook;
use Plugin\ws5_mollie\lib\PluginHelper;
use JTL\Events\Event;

require_once __DIR__ . '/vendor/autoload.php';

class Bootstrap extends \WS\JTL5\V1_0_16\Bootstrap
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

        $this->listen(HOOK_INDEX_NAVI_HEAD_POSTGET, [Queue::class, 'headPostGet']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [Queue::class, 'xmlBestellStatus']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, [Queue::class, 'xmlBearbeiteStorno']);
        
        if (PluginHelper::getSetting('useCustomerAPI') === 'C') {
            $this->listen(HOOK_CHECKBOX_CLASS_GETCHECKBOXFRONTEND, [Checkbox::class, 'execute']);
        }

        //routes
        if (PluginHelper::getSetting('queue') === 'async') {
            $this->listen(HOOK_ROUTER_PRE_DISPATCH, function ($args) {
                /** @var Router $router */
                $router = $args['router'];
                $router->addRoute('/' . self::getPlugin()->getPluginID() . '/queue', [\Plugin\ws5_mollie\lib\Queue::class, 'runAsynchronous'], null, ['POST']);
            });
        }
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
