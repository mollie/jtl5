<?php

namespace Plugin\ws5_mollie\lib;

use JTL\Model\DataModel;
use Mollie\Api\Exceptions\ApiException;
use Plugin\ws5_mollie\lib\Model\CustomerModel;
use Plugin\ws5_mollie\lib\Traits\Jsonable;

/**
 * Class Customer
 * @package Plugin\ws5_mollie\lib
 */
class Customer
{
    use Jsonable;

    public $name;
    public $email;
    public $locale;
    public $metadata;

    /**
     * @param \JTL\Customer\Customer $oKunde
     * @return string|null
     * @throws \Exception
     */
    public static function createOrUpdate(\JTL\Customer\Customer $oKunde): ?string
    {

        $mCustomer = CustomerModel::loadByAttributes([
            'kunde' => $oKunde->kKunde,
        ], \Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_NEW);


        $api = new MollieAPI(MollieAPI::getMode());

        if (!$mCustomer->getCustomerId()) {

            if (!array_key_exists('mollie_create_customer', $_SESSION['cPost_arr']) || $_SESSION['cPost_arr']['mollie_create_customer'] !== 'on') {
                return null;
            }

            $customer = new self();
        } else {
            try {
                $customer = $api->getClient()->customers->get($mCustomer->getCustomerId());
            } catch (ApiException $e) {
                $customer = new self();
            }

        }

        $customer->name = trim($oKunde->cVorname . ' ' . $oKunde->cNachname);
        $customer->email = $oKunde->cMail;
        $customer->locale = Locale::getLocale(\Session::get('cISOSprache', 'ger'), $oKunde->cLand);
        $customer->metadata = (object)[
            'kKunde' => $oKunde->getID(),
            'kKundengruppe' => $oKunde->getGroupID(),
            'cKundenNr' => $oKunde->cKundenNr,
        ];

        if ($customer instanceof \Mollie\Api\Resources\Customer) {
            $customer->update();
        } else {
            try {
                $customer = $api->getClient()->customers->create($customer->toArray());
                $mCustomer->setCustomerId($customer->id);
                $mCustomer->save();
            } catch (ApiException $e) {
                return null;
            }
        }

        return $mCustomer->getCustomerId();
    }

}