<?php


namespace Plugin\ws5_mollie\paymentmethod;


use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\PaymentMethod;

class Belfius extends PaymentMethod
{

    public const ALLOW_PAYMENT_BEFORE_ORDER = true;

    public const METHOD = \Mollie\Api\Types\PaymentMethod::BELFIUS;

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        return [];
    }

}