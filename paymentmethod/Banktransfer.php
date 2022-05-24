<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use JTL\Session\Frontend;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Locale;
use Plugin\ws5_mollie\lib\PaymentMethod;

class Banktransfer extends PaymentMethod
{
    public const ALLOW_AUTO_STORNO = false;

    public const METHOD = \Mollie\Api\Types\PaymentMethod::BANKTRANSFER;


    public function generatePUI(AbstractCheckout $checkout): string
    {

        if(self::Plugin('ws5_mollie')->getConfig()->getValue($this->moduleID . '_usePUI') === 'N'){
            return false;
        }

        $template = self::Plugin('ws5_mollie')->getLocalization()->getTranslation('banktransferPUI');

        return str_replace(
            [
                '%amount%',
                '%expiresAt%',
                '%bankName%',
                '%bankAccount%',
                '%bankBic%',
                '%transferReference%'
            ],
            [
                "{$checkout->getMollie()->amount->value} {$checkout->getMollie()->amount->currency}",
                date('d.m.Y', strtotime($checkout->getMollie()->expiresAt)),
                $checkout->getMollie()->details->bankName,
                $checkout->getMollie()->details->bankAccount,
                $checkout->getMollie()->details->bankBic,
                $checkout->getMollie()->details->transferReference
            ],
            $template
        );
    }

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        $paymentOptions = [];
        if ($apiType === 'payment') {
            $paymentOptions['billingEmail'] = $order->oRechnungsadresse->cMail;
            $paymentOptions['locale']       = Locale::getLocale(Frontend::get('cISOSprache', 'ger'), $order->oRechnungsadresse->cLand);
            $dueDays                        = (int)self::Plugin('ws5_mollie')->getConfig()->getValue($this->moduleID . '_dueDays');
            if ($dueDays > 3) {
                $paymentOptions['dueDate'] = date('Y-m-d', strtotime("+{$dueDays} DAYS"));
            }
        }

        return $paymentOptions;
    }
}
