<?php

use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Queue;

try {
        require_once __DIR__ . '/../../vendor/autoload.php';

        ifndef('MOLLIE_QUEUE_MAX', 3);
        Queue::run(MOLLIE_QUEUE_MAX);
        AbstractCheckout::sendReminders();

} catch (Exception $e) {
    Shop::Container()->getLogService()->error($e->getMessage() . " (Trace: {$e->getTraceAsString()})");
}

