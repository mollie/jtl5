<?php

/**
 * @copyright 2020 WebStollen GmbH
 */

namespace ws5_mollie;

use JTL\Model\DataModel;
use JTL\Plugin\Payment\LegacyMethod;
use Mollie\Api\Resources\Order as MollieOrder;
use ws5_mollie\Model\OrderModel;
use ws5_mollie\Order\Address;
use ws5_mollie\Order\Amount;
use ws5_mollie\Order\OrderLine;
use ws5_mollie\Traits\Jsonable;

class Order implements \JsonSerializable
{
    use Jsonable;

    public $locale;
    public $amount;
    public $orderNumber;
    public $metadata;
    public $redirectUrl;
    public $webhookUrl;
    public $method;
    /**
     * @var Address
     */
    public $billingAddress;
    public $consumerDateOfBirth;
    /**
     * @var OrderLine[]
     */
    public $lines;

    /**
     * @var \stdClass|null;
     */
    public $payment;

    /**
     * @var Address|null
     */
    public $shippingAddress;

    protected function __construct()
    {

    }

    /**
     * @param \Bestellung $oBestellung
     * @return MollieOrder
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public static function createOrder(\Bestellung $oBestellung, $customerID = null): ?MollieOrder
    {

        $api = MollieAPI::API(MollieAPI::getMode());

        // PayAgain, order already existing?
        if ($oBestellung->kBestellung > 0) {
            try {
                $order = OrderModel::loadByAttributes(['bestellung' => $oBestellung->kBestellung], \Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_FAIL);
                return $api->orders->get($order->getOrderId(), ['embed' => 'payments']);
            } catch (\Exception $e) {
            }
        }


        /** @var $data Order */
        list($data, $hash) = self::factory($oBestellung);

        if ($customerID) {
            if (!$data->payment) {
                $data->payment = new \stdClass();
            }
            $data->payment->customerId = $customerID;
        }


        $order = $api->orders->create($data->toArray());
        //$payments = $order->payments();

        $orderModel = OrderModel::loadByAttributes([
            'orderId' => $order->id,
        ], \Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_NEW);

        $orderModel->setBestellung($oBestellung->kBestellung);
        //$orderModel->setOrderId($order->id);
        //$orderModel->setTransactionId($payments && $payments->count() === 1 && $payments->hasNext() ? $payments->next()->id : '');
        $orderModel->setStatus($order->status);
        $orderModel->setHash($hash);
        $orderModel->setTest(MollieAPI::isTest());
        $orderModel->setSynced(false);

        if (!$orderModel->save()) {
            throw new \Exception('Could not save OrderModel.');
        }

        return $order;
    }

    /**
     * @param \Bestellung $oBestellung
     * @return array
     * @throws \Exception
     */
    public static function factory(\Bestellung $oBestellung): array
    {
        /**
         * @todo
         * - payment->cardToken
         * - payment->customerId
         */
        $_currFactor = \Session::getCurrency()->getConversionFactor();
        $oPaymentMethod = LegacyMethod::create($oBestellung->Zahlungsart->cModulId);
        if (!$oPaymentMethod) {
            throw new \Exception('Could not load PaymentMethod!');
        }

        $hash = $oPaymentMethod->generateHash($oBestellung);

        $data = new self();
        $data->locale = Locale::getLocale(\Session::get('cISOSprache', 'ger'), \Session::getCustomer()->cLand);
        $data->amount = new Amount($oBestellung->fGesamtsumme, $oBestellung->Waehrung, true, true);
        $data->orderNumber = $oBestellung->cBestellNr;
        $data->metadata = [
            'kBestellung' => $oBestellung->kBestellung,
            'kKunde' => $oBestellung->kKunde,
            'kKundengruppe' => \Session::getCustomerGroup()->getID(),
            'cHash' => $oPaymentMethod->generateHash($oBestellung),
        ];
        if ($oPaymentMethod->duringCheckout) {
            $data->metadata['tmpOrderNumber'] = $oBestellung->cBestellNr;
        }
        $data->redirectUrl = $oPaymentMethod->getReturnURL($oBestellung);
        $data->webhookUrl = $oPaymentMethod->getNotificationURL($hash);

        /** @var PaymentMethod $oPaymentMethod */
        /** @noinspection NotOptimalIfConditionsInspection */
        if (defined(get_class($oPaymentMethod) . '::METHOD') && $oPaymentMethod::METHOD !== '') {
            $data->method = $oPaymentMethod::METHOD;
        }

        $data->billingAddress = Address::factory($oBestellung->oRechnungsadresse);
        if ($oBestellung->Lieferadresse !== null) {
            $data->shippingAddress = Address::factory($oBestellung->Lieferadresse);
        }

        if (
            !empty(\Session::getCustomer()->dGeburtstag)
            && \Session::getCustomer()->dGeburtstag !== '0000-00-00'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim(\Session::getCustomer()->dGeburtstag))
        ) {
            $data->consumerDateOfBirth = trim(\Session::getCustomer()->dGeburtstag);
        }

        $data->lines = [];
        foreach ($oBestellung->Positionen as $oPosition) {
            $data->lines[] = OrderLine::factory($oPosition, $oBestellung->Waehrung);
        }

        if ($oBestellung->GuthabenNutzen && $oBestellung->fGuthaben > 0) {
            $data->lines[] = OrderLine::getCredit($oBestellung);
        }

        if ($comp = OrderLine::getRoundingCompensation($data->lines, $data->amount, $oBestellung->Waehrung)) {
            $data->lines[] = $comp;
        }

        return [$data, $hash];
    }
}