<?php
/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\paymentmethod;

use Plugin\ws5_mollie\lib\PaymentMethod;

require_once __DIR__ . '/../vendor/autoload.php';

class ApplePay extends PaymentMethod
{
    public const METHOD = \Mollie\Api\Types\PaymentMethod::APPLEPAY;

    public function isSelectable(): bool
    {
        return \Plugin\ws5_mollie\lib\Hook\ApplePay::isAvailable() && parent::isSelectable();
    }

}
