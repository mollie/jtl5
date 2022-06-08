<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Payment;

use JsonSerializable;
use WS\JTL5\Traits\Jsonable;

class Address implements JsonSerializable
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
        $this->streetAndNumber = html_entity_decode($address->cStrasse . ' ' . $address->cHausnummer);
        $this->postalCode      = html_entity_decode($address->cPLZ);
        $this->city            = html_entity_decode($address->cOrt);
        $this->country         = html_entity_decode($address->cLand);

        if (
            isset($adresse->cAdressZusatz)
            && trim($adresse->cAdressZusatz) !== ''
        ) {
            $this->streetAdditional = html_entity_decode(trim($adresse->cAdressZusatz));
        }
    }
}
