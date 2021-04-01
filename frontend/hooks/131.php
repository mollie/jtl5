<?php

use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Queue;

try {

    if (!array_key_exists('mollie_queue_disabled', $_SESSION)) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        ifndef('MOLLIE_QUEUE_MAX', 3);
        Queue::run(MOLLIE_QUEUE_MAX);
        AbstractCheckout::sendReminders();
    }

} catch (Exception $e) {
    $_SESSION['mollie_queue_disabled'] = true;
    Shop::Container()->getLogService()->error($e->getMessage() . " (Trace: {$e->getTraceAsString()})");
}

