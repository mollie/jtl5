<?php
/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\paymentmethod;

use Plugin\ws5_mollie\lib\PaymentMethod;

require_once __DIR__ . '/../vendor/autoload.php';

class Przelewy24 extends PaymentMethod
{
    public const METHOD = \Mollie\Api\Types\PaymentMethod::PRZELEWY24;


}
