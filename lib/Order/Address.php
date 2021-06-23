<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Order;

/**
 * Class Address
 * @package Mollie\Order
 */
class Address extends \Plugin\ws5_mollie\lib\Payment\Address
{
    /**
     * @var null|string
     */
    public $organizationName;

    /**
     * @var null|string
     */
    public $title;

    /**
     * @var string
     */
    public $givenName;

    /**
     * @var string
     */
    public $familyName;

    /**
     * @var string
     */
    public $email;

    /**
     * @var null|string
     */
    public $phone;

    /**
     * Address constructor.
     * @param $address
     */
    public function __construct($address)
    {
        parent::__construct($address);

        $this->title      = trim(($address->cAnrede === 'm' ? \Shop::Lang()->get('mr') : \Shop::Lang()->get('mrs')) . ' ' . $address->cTitel) ?? null;
        $this->givenName  = $address->cVorname;
        $this->familyName = $address->cNachname;
        $this->email      = $address->cMail ?? null;

        if ($organizationName = trim($address->cFirma)) {
            $this->organizationName = $organizationName;
        }
    }

    /**
     * @param \JTL\Checkout\Adresse|\stdClass $address
     * @return Address
     * @deprecated
     */
    public static function factory($address): self
    {
        return new self($address);
    }
}
