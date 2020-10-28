<?php


namespace Plugin\ws5_mollie\lib;


use JTL\Model\DataModel;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Mollie\Api\Exceptions\ApiException;
use Plugin\ws5_mollie\lib\Model\CustomerModel;
use Plugin\ws5_mollie\lib\Traits\Jsonable;

class Customer
{
    use Jsonable;

    public $name;
    public $email;
    public $locale;
    public $metadata;


    public static function createOrUpdate(\JTL\Customer\Customer $oKunde): ?string
    {
        $mCustomer = CustomerModel::loadByAttributes([
            'kunde' => $oKunde->kKunde,
        ], \Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_NEW);


        $api = MollieAPI::API(MollieAPI::getMode());

        if (!$mCustomer->getCustomerId()) {
            $customer = new self();
        } else {
            try {
                $customer = $api->customers->get($mCustomer->getCustomerId());
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

        if($customer instanceof \Mollie\Api\Resources\Customer){
            $customer->update();
        }else{
            try{
                $customer = $api->customers->create($customer->toArray());
                $mCustomer->setCustomerId($customer->id);
                $mCustomer->save();
            }catch (ApiException $e){
                return null;
            }
        }

        return $mCustomer->getCustomerId();
    }

}