<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Order;

use JTL\Shop;

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

        $this->title = html_entity_decode(substr(trim(($address->cAnrede === 'm' ? Shop::Lang()->get('mr') : Shop::Lang()->get('mrs')) . ' ' . $address->cTitel), 0, 20)) ?? null;
        $this->givenName = html_entity_decode($address->cVorname);
        $this->familyName = html_entity_decode($address->cNachname);
        $this->email = html_entity_decode($address->cMail) ?? null;

        if ($organizationName = trim($address->cFirma)) {
            $this->organizationName = html_entity_decode($organizationName);
        }
    }
}
