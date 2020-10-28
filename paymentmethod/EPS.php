<?php
/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\paymentmethod;

use Plugin\ws5_mollie\lib\PaymentMethod;

require_once __DIR__ . '/../vendor/autoload.php';

class EPS extends PaymentMethod
{
    public const METHOD = \Mollie\Api\Types\PaymentMethod::EPS;
}
