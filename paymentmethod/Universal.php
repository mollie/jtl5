<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\PaymentMethod;

require_once __DIR__ . '/../vendor/autoload.php';

class Universal extends PaymentMethod
{
    public const ALLOW_AUTO_STORNO = false;

    public const METHOD = '';

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        return [];
    }
}
