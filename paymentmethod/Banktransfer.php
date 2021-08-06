<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\Locale;
use Plugin\ws5_mollie\lib\PaymentMethod;
use Session;

class Banktransfer extends PaymentMethod
{
    public const ALLOW_AUTO_STORNO = false;

    public const METHOD = \Mollie\Api\Types\PaymentMethod::BANKTRANSFER;

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        $paymentOptions = [];
        if ($apiType === 'payment') {
            $paymentOptions['billingEmail'] = $order->oRechnungsadresse->cMail;
            $paymentOptions['locale']       = Locale::getLocale(Session::get('cISOSprache', 'ger'), $order->oRechnungsadresse->cLand);
        }
        $dueDays = (int)self::Plugin('ws5_mollie')->getConfig()->getValue($this->moduleID . '_dueDays');
        if ($dueDays > 3) {
            $paymentOptions['dueDate'] = date('Y-m-d', strtotime("+{$dueDays} DAYS"));
        }

        return $paymentOptions;
    }
}
