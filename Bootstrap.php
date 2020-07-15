<?php
/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;

class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher)
    {
        require_once __DIR__ . '/vendor/autoload.php';
        try {
            $dispatcher->listen('shop.hook.' . HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB, function ($args_arr) {
                if (array_key_exists('oBestellung', $args_arr)) {
                    $args_arr['oBestellung']->cAbgeholt = 'Y';
                }
            });
        } catch (\Exception $e) {
            if (\Shop::isFrontend()) {
                \Shop::Container()->getBackendLogService()->addCritical($e->getMessage());
            } else {
                \Shop::Container()->getLogService()->addCritical($e->getMessage());
            }
        }
    }
}
