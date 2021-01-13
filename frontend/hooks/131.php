<?php

use Plugin\ws5_mollie\lib\Queue;

try {
    ifndef('MOLLIE_QUEUE_MAX', 3);
    Queue::run(MOLLIE_QUEUE_MAX);
} catch (Exception $e) {
    // TODO: LOG!
    throw $e;
}

