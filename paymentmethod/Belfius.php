<?php


namespace Plugin\ws5_mollie\paymentmethod;


use Plugin\ws5_mollie\lib\PaymentMethod;

class Belfius extends PaymentMethod
{
    public const METHOD = \Mollie\Api\Types\PaymentMethod::BELFIUS;
}