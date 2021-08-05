<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib;

use Mollie\Api\Exceptions\ApiException;
use Plugin\ws5_mollie\lib\Model\CustomerModel;
use WS\JTL5\Traits\Jsonable;

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

    public static function createOrUpdate(\JTL\Customer\Customer $oKunde): ?string
    {
        $mCustomer = CustomerModel::fromID($oKunde->kKunde, 'kKunde');

        $api = new MollieAPI(MollieAPI::getMode());

        if (!$mCustomer->customerId) {
            if (!array_key_exists('mollie_create_customer', $_SESSION['cPost_arr']) || $_SESSION['cPost_arr']['mollie_create_customer'] !== 'on') {
                return null;
            }

            $customer = new self();
        } else {
            try {
                $customer = $api->getClient()->customers->get($mCustomer->customerId);
            } catch (ApiException $e) {
                $customer = new self();
            }
        }

        $customer->name     = trim($oKunde->cVorname . ' ' . $oKunde->cNachname);
        $customer->email    = $oKunde->cMail;
        $customer->locale   = Locale::getLocale(\Session::get('cISOSprache', 'ger'), $oKunde->cLand);
        $customer->metadata = (object)[
            'kKunde'        => $oKunde->getID(),
            'kKundengruppe' => $oKunde->getGroupID(),
            'cKundenNr'     => $oKunde->cKundenNr,
        ];

        if ($customer instanceof \Mollie\Api\Resources\Customer) {
            $customer->update();
        } else {
            try {
                $customer              = $api->getClient()->customers->create($customer->toArray());
                $mCustomer->customerId = $customer->id;
                $mCustomer->save();
            } catch (ApiException $e) {
                return null;
            }
        }

        return $mCustomer->customerId;
    }
}
