<?php


namespace ws5_mollie\Order;

use ws5_mollie\Traits\Jsonable;

/**
 * Class Address
 * @package Mollie\Order
 */
class Address implements \JsonSerializable
{

    use Jsonable;

    /**
     * @var string|null
     */
    public $organizationName;

    /**
     * @var string|null
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
     * @var string|null
     */
    public $phone;

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

        $address->title = trim(($adresse->cAnrede === 'm' ? \Shop::Lang()->get('mr') : \Shop::Lang()->get('mrs')) . ' ' . $adresse->cTitel) ?? null;
        $address->givenName = $adresse->cVorname;
        $address->familyName = $adresse->cNachname;
        $address->email = $adresse->cMail ?? null;
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

        if ($organizationName = trim($adresse->cFirma)) {
            $address->organizationName = $organizationName;
        }

        return $address;

    }


}