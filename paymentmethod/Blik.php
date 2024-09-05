<?php

/**
 * @copyright 2024 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\PaymentMethod;

class Blik extends PaymentMethod
{
    public const ALLOW_PAYMENT_BEFORE_ORDER = true;

    public const METHOD = \Mollie\Api\Types\PaymentMethod::BLIK;

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        return [];
    }
}