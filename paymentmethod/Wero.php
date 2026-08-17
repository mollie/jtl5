<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\PaymentMethod;

require_once __DIR__ . '/../vendor/autoload.php';

class Wero extends PaymentMethod
{
    public const ALLOW_PAYMENT_BEFORE_ORDER = true;
    // TODO: update sdk
    // public const METHOD = \Mollie\Api\Types\PaymentMethod::WERO;

    public const METHOD = 'wero';

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        return [];
    }
}
