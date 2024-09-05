<?php

namespace Plugin\ws5_mollie\paymentmethod;

use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\PaymentMethod;

class Billie extends PaymentMethod
{
    public const ALLOW_PAYMENT_BEFORE_ORDER = true;
    public const METHOD = 'billie';

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        /**
         * TODO:
         * optional parameters could be added
         * registrationNumber, vatNumber, entityType
         */
        $paymentOptions = [];
        return $paymentOptions;
    }

    public function isSelectable(): bool
    {
        $company = (isset($_POST['firma']) && strlen($_POST['firma'])) || (isset($_SESSION['Kunde']->cFirma) && strlen($_SESSION['Kunde']->cFirma));
        return $company && parent::isSelectable();
    }

}