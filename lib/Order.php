<?php

/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib;

use Bestellung;
use Exception;
use JsonSerializable;
use JTL\Checkout\Lieferschein;
use JTL\Checkout\Lieferscheinpos;
use JTL\Checkout\Versand;
use JTL\Model\DataModel;
use JTL\Plugin\Payment\LegacyMethod;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Types\OrderStatus;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Order\Address;
use Plugin\ws5_mollie\lib\Order\Amount;
use Plugin\ws5_mollie\lib\Order\OrderLine as WSOrderLine;
use Plugin\ws5_mollie\lib\Traits\Jsonable;
use Plugin\ws5_mollie\lib\Traits\Plugin;
use RuntimeException;
use Session;
use Shop;
use stdClass;

class Order implements JsonSerializable
{
    use Jsonable;
    use Plugin;

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
     * @var stdClass|null;
     */
    public $payment;

    /**
     * @var Address|null
     */
    public $shippingAddress;

    protected function __construct()
    {

    }

    public static function isMollie(int $kZahlungsart): bool
    {
        return ($res = Shop::Container()->getDB()->executeQueryPrepared('SELECT cModulId FROM tzahlungsart WHERE kZahlungsart = :kZahlungsart AND cModulId LIKE :cModulId;', [
                ':kZahlungsart' => $kZahlungsart,
                ':cModulId' => sprintf('kPlugin_%d_%%', self::Plugin()->getID())
            ], 1)) && $res->cModulId;
    }

    /**
     * @param Bestellung $oBestellung
     * @param MollieOrder $mollie
     * @param bool $newStatus
     * @return array|null
     */
    public static function getShipmentOptions(Bestellung $oBestellung, MollieOrder $mollie, bool $newStatus): ?array
    {

        $options = [];

        if (!$newStatus && (int)$oBestellung->cStatus !== BESTELLUNG_STATUS_TEILVERSANDT) {
            return null;
        }

        $getSentQuantity = static function (stdClass $line, Bestellung $oBestellung): int {
            if ($line->sku === null) {
                return 1;
            }
            /** @var \WarenkorbPos $oPosition */
            foreach ($oBestellung->Positionen as $oPosition) {
                if ($oPosition->cArtNr === $line->sku) {
                    $sent = 0;
                    /** @var Lieferschein $oLieferschein */
                    foreach ($oBestellung->oLieferschein_arr as $oLieferschein) {
                        /** @var Lieferscheinpos $oLieferscheinPos */
                        foreach ($oLieferschein->oLieferscheinPos_arr as $oLieferscheinPos) {
                            if ($oLieferscheinPos->getBestellPos() === $oPosition->kBestellpos) {
                                $sent += $oLieferscheinPos->getAnzahl();
                            }
                        }
                    }
                    return $sent;
                }
            }
            return 0;
        };

        if (isset($oBestellung->oLieferschein_arr)
            && ($nLS = count($oBestellung->oLieferschein_arr) - 1) >= 0
            && isset($oBestellung->oLieferschein_arr[$nLS]->oVersand_arr)) {
            if (($nV = count($oBestellung->oLieferschein_arr[$nLS]->oVersand_arr) - 1) >= 0) {
                /** @var Versand $oVersand */
                $oVersand = $oBestellung->oLieferschein_arr[$nLS]->oVersand_arr[$nV];
                $tracking = new stdClass();
                $tracking->carrier = trim($oVersand->getLogistik());
                $tracking->code = trim($oVersand->getIdentCode());
                $tracking->url = trim($oVersand->getLogistikURL()) ?: null;
                if ($tracking->code && $tracking->carrier) {
                    $options['tracking'] = $tracking;
                }
            }
        }

        switch ($oBestellung->cStatus) {
            case BESTELLUNG_STATUS_VERSANDT:
                //    $options['lines'] = [];
                //    break;
            case BESTELLUNG_STATUS_TEILVERSANDT:

                $options['lines'] = [];
                foreach ($mollie->lines as $line) {
                    if (($x = $getSentQuantity($line, $oBestellung)) > 0) {
                        if (($quantity = min($x - $line->quantityShipped, $line->shippableQuantity)) > 0) {
                            $options['lines'][] = (object)[
                                'id' => $line->id,
                                'quantity' => $quantity
                            ];
                        }
                    }
                }
                break;
        }
        return $options;
    }


    /**
     * @param Bestellung $oBestellung
     * @param array $paymentOptions
     * @return MollieOrder
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public static function createOrder(Bestellung $oBestellung, array $paymentOptions = []): ?MollieOrder
    {

        $api = null;
        $orderModel = null;
        // PayAgain, order already existing?
        if ($oBestellung->kBestellung > 0) {
            try {
                $orderModel = OrderModel::loadByAttributes(
                    ['bestellung' => $oBestellung->kBestellung],
                    Shop::Container()->getDB(),
                    DataModel::ON_NOTEXISTS_FAIL);
                $api = MollieAPI::API($orderModel->test);
                $order = $api->orders->get($orderModel->getOrderId(), ['embed' => 'payments']);
                if ($order->status === OrderStatus::STATUS_CREATED) {
                    return $order;
                }
            } catch (Exception $e) {
            }
        }
        if (!$api) {
            $api = MollieAPI::API(MollieAPI::getMode());
        }
        /** @var $data Order */
        [$data, $hash] = self::factory($oBestellung);

        if (count($paymentOptions)) {
            $data->payment = (object)$paymentOptions;
        }

        /*if ((self::Plugin()->getConfig()->getValue('resetMethod') === 'on')
            && strpos($_SERVER['PHP_SELF'], 'bestellab_again.php') !== false) {
            unset($data->method);
        }*/

        $order = $api->orders->create($data->toArray());
        //$payments = $order->payments();
        if (!$orderModel) {
            $orderModel = OrderModel::loadByAttributes([
                'orderId' => $order->id,
            ], Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_NEW);
        }

        $orderModel->setBestellung($oBestellung->kBestellung);
        $orderModel->setBestellNr($oBestellung->cBestellNr);
        $orderModel->setLocale($order->locale);
        $orderModel->setAmount($order->amount->value);
        $orderModel->setMethod($order->method);
        $orderModel->setCurrency($order->amount->currency);
        $orderModel->setOrderId($order->id);
        //$orderModel->setTransactionId($payments && $payments->count() === 1 && $payments->hasNext() ? $payments->next()->id : '');
        $orderModel->setStatus($order->status);
        $orderModel->setHash($hash);
        $orderModel->setTest(MollieAPI::getMode());
        $orderModel->setSynced(false);

        if (!$orderModel->save()) {
            throw new RuntimeException('Could not save OrderModel.');
        }

        return $order;
    }

    /**
     * @param Bestellung $oBestellung
     * @return array
     * @throws Exception
     */
    public static function factory(Bestellung $oBestellung): array
    {
        /**
         * @todo
         * - payment->cardToken
         * - payment->customerId
         */
        //$_currFactor = Session::getCurrency()->getConversionFactor();
        $oPaymentMethod = LegacyMethod::create($oBestellung->Zahlungsart->cModulId);
        if (!$oPaymentMethod) {
            throw new \RuntimeException('Could not load PaymentMethod!');
        }

        $hash = $oPaymentMethod->generateHash($oBestellung);

        $data = new self();
        $data->locale = Locale::getLocale(Session::get('cISOSprache', 'ger'), Session::getCustomer()->cLand);
        $data->amount = new Amount($oBestellung->fGesamtsumme, $oBestellung->Waehrung, true, true);
        $data->orderNumber = $oBestellung->cBestellNr;
        $data->metadata = [
            'kBestellung' => $oBestellung->kBestellung,
            'kKunde' => $oBestellung->kKunde,
            'kKundengruppe' => Session::getCustomerGroup()->getID(),
            'cHash' => $oPaymentMethod->generateHash($oBestellung),
        ];
        if ($oPaymentMethod->duringCheckout) {
            $data->metadata['tmpOrderNumber'] = $oBestellung->cBestellNr;
        }
        $data->redirectUrl = $oPaymentMethod->getReturnURL($oBestellung);
        $data->webhookUrl = Shop::getURL(true) . '/?mollie=1'; //$oPaymentMethod->getNotificationURL($hash);

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
            !empty(Session::getCustomer()->dGeburtstag)
            && Session::getCustomer()->dGeburtstag !== '0000-00-00'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim(Session::getCustomer()->dGeburtstag))
        ) {
            $data->consumerDateOfBirth = trim(Session::getCustomer()->dGeburtstag);
        }

        $data->lines = [];
        foreach ($oBestellung->Positionen as $oPosition) {
            $data->lines[] = WSOrderLine::factory($oPosition, $oBestellung->Waehrung);
        }

        if ($oBestellung->GuthabenNutzen && $oBestellung->fGuthaben > 0) {
            $data->lines[] = WSOrderLine::getCredit($oBestellung);
        }

        if ($comp = WSOrderLine::getRoundingCompensation($data->lines, $data->amount, $oBestellung->Waehrung)) {
            $data->lines[] = $comp;
        }

        return [$data, $hash];
    }

    public static function update(\Mollie\Api\Resources\Order $order): bool
    {
        $orderModel = OrderModel::loadByAttributes(
            ['orderId' => $order->id],
            Shop::Container()->getDB(),
            DataModel::ON_NOTEXISTS_FAIL);

        $orderModel->setLocale($order->locale);
        $orderModel->setAmount($order->amount->value);
        $orderModel->setMethod($order->method);
        $orderModel->setCurrency($order->amount->currency);
        $orderModel->setOrderId($order->id);
        $orderModel->setStatus($order->status);

        return $orderModel->save();

    }
}
