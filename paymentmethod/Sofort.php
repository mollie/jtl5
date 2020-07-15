<?php
/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use JTL\Plugin\Plugin;
use ws5_mollie\Order;
use ws5_mollie\PaymentMethod;

require_once __DIR__ . '/../vendor/autoload.php';

class Sofort extends PaymentMethod
{
    const METHOD = \Mollie\Api\Types\PaymentMethod::SOFORT;


}
