<?php


namespace Plugin\ws5_mollie\paymentmethod;


use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\PaymentMethod;

class KBC extends PaymentMethod
{
    public const METHOD = \Mollie\Api\Types\PaymentMethod::KBC;

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        return ['description' => substr($order->cBestellNr, 0, 13)];
    }

}