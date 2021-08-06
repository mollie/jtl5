<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Payment;

use WS\JTL5\Traits\Jsonable;

class Address implements \JsonSerializable
{
    use Jsonable;

    /**
     * @var string
     */
    public $streetAndNumber;

    /**
     * @var null|string
     */
    public $streetAdditional;

    /**
     * @var string
     */
    public $postalCode;

    /**
     * @var string
     */
    public $city;

    /**
     * @var null|string
     */
    public $region;

    /**
     * @var string
     */
    public $country;

    /**
     * Address constructor.
     * @param $address
     */
    public function __construct($address)
    {
        $this->streetAndNumber = $address->cStrasse . ' ' . $address->cHausnummer;
        $this->postalCode      = $address->cPLZ;
        $this->city            = $address->cOrt;
        $this->country         = $address->cLand;

        if (
            isset($adresse->cAdressZusatz)
            && trim($adresse->cAdressZusatz) !== ''
        ) {
            $this->streetAdditional = trim($adresse->cAdressZusatz);
        }
    }
}
