<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\Payment\Address;
use Plugin\ws5_mollie\lib\PaymentMethod;

require_once __DIR__ . '/../vendor/autoload.php';

class PayPal extends PaymentMethod
{
    public const ALLOW_PAYMENT_BEFORE_ORDER = true;

    public const METHOD = \Mollie\Api\Types\PaymentMethod::PAYPAL;

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        $paymentOptions = [];

        if ($apiType === 'payment') {
            if ($order->Lieferadresse !== null) {
                if (!$order->Lieferadresse->cMail) {
                    $order->Lieferadresse->cMail = $order->oRechnungsadresse->cMail;
                }
                $paymentOptions['shippingAddress'] = new Address($order->Lieferadresse);
            }
            $paymentOptions['description'] = 'Order ' . $order->cBestellNr;
        }

        return $paymentOptions;
    }
}
