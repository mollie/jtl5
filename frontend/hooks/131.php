<?php

use JTL\Shop;
use Plugin\ws5_mollie\lib\Queue;

try {
    ifndef('MOLLIE_QUEUE_MAX', 3);
    Queue::run(MOLLIE_QUEUE_MAX);
} catch (Exception $e) {
    Shop::Container()->getLogService()->error($e->getMessage() . " (Trace: {$e->getTraceAsString()})");
}

