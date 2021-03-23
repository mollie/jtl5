<?php


namespace Plugin\ws5_mollie\lib\Payment;

use Plugin\ws5_mollie\lib\Traits\Jsonable;

class Address implements \JsonSerializable
{

    use Jsonable;


    /**
     * @var string
     */
    public $streetAndNumber;

    /**
     * @var string|null
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
     * @var string|null
     */
    public $region;

    /**
     * @var string
     */
    public $country;

    /**
     * @param \Adresse|\stdClass $adresse
     * @return Address
     */
    public static function factory($adresse): Address
    {
        $address = new self();

        $address->streetAndNumber = $adresse->cStrasse . ' ' . $adresse->cHausnummer;
        $address->postalCode = $adresse->cPLZ;
        $address->city = $adresse->cOrt;
        $address->country = $adresse->cLand;

        if (
            isset($adresse->cAdressZusatz)
            && trim($adresse->cAdressZusatz) !== ''
        ) {
            $address->streetAdditional = trim($adresse->cAdressZusatz);
        }

        return $address;

    }


}