<?php

/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib;

use Bestellung;
use Exception;
use JsonSerializable;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Lieferschein;
use JTL\Checkout\Lieferscheinpos;
use JTL\Checkout\Versand;
use JTL\Mail\Mail\Mail;
use JTL\Mail\Mailer;
use JTL\Model\DataModel;
use JTL\Plugin\Payment\LegacyMethod;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
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

    /**
     * @param int $kBestellung
     * @return bool
     */
    public static function isMollie(int $kBestellung, bool $checkZA = false): bool
    {
        if ($checkZA) {
            $res = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM tzahlungsart WHERE cModulId LIKE :cModulId AND kZahlungsart = :kZahlungsart', [
                ':kZahlungsart' => $kBestellung,
                ':cModulId' => 'kPlugin_' . self::Plugin()->getID() . '%'
            ], 1);
            return $res ? true : false;
        }

        return ($res = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId FROM xplugin_ws5_mollie_orders WHERE kBestellung = :kBestellung;', [
                ':kBestellung' => $kBestellung,
            ], 1)) && $res->kId;
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
                $options['lines'] = [];
                break;
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

    public static function createPayment(Bestellung $oBestellung, array $options = []): ?Payment
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
                $payment = $api->payments->get($orderModel->getOrderId());
                if ($payment->status === PaymentStatus::STATUS_OPEN) {
                    return $payment;
                }
            } catch (Exception $e) {
            }
        }
        if (!$api) {
            $api = MollieAPI::API(MollieAPI::getMode());
        }

        [$data, $hash] = self::factoryPayment($oBestellung);
        $data = array_merge($data, $options);

        if (strpos($_SERVER['PHP_SELF'], 'bestellab_again') !== false
            && self::Plugin()->getConfig()->getValue('resetMethod') === 'on') {
            unset($data['method']);
        }

        $payment = $api->payments->create($data);
        if (!$orderModel) {
            $orderModel = OrderModel::loadByAttributes([
                'orderId' => $payment->id,
            ], Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_NEW);
            $orderModel->setTest(MollieAPI::getMode());
        }

        $orderModel->setBestellung($oBestellung->kBestellung);
        $orderModel->setBestellNr($oBestellung->cBestellNr);
        $orderModel->setLocale($payment->locale);
        $orderModel->setAmount($payment->amount->value);
        $orderModel->setMethod($payment->method);
        $orderModel->setCurrency($payment->amount->currency);
        $orderModel->setOrderId($payment->id);
        $orderModel->setStatus($payment->status);
        $orderModel->setHash($hash);
        $orderModel->setSynced(self::Plugin()->getConfig()->getValue('onlyPaid') !== 'on');

        if (!$orderModel->save()) {
            throw new RuntimeException('Could not save OrderModel.');
        }
        return $payment;


    }

    public static function factoryPayment(Bestellung $oBestellung): array
    {
        $oPaymentMethod = LegacyMethod::create($oBestellung->Zahlungsart->cModulId);
        if (!$oPaymentMethod) {
            throw new \RuntimeException('Could not load PaymentMethod!');
        }
        $hash = $oPaymentMethod->generateHash($oBestellung);

        $data = [];
        $data['amount'] = new Amount($oBestellung->fGesamtsumme, $oBestellung->Waehrung, true, true);
        $data['description'] = 'Order ' . $oBestellung->cBestellNr;
        $data['redirectUrl'] = $oPaymentMethod->getReturnURL($oBestellung);
        $data['webhookUrl'] = Shop::getURL(true) . '/?mollie=1';
        $data['locale'] = Locale::getLocale(Session::get('cISOSprache', 'ger'), Session::getCustomer()->cLand);
        /** @var PaymentMethod $oPaymentMethod */
        /** @noinspection NotOptimalIfConditionsInspection */
        if (defined(get_class($oPaymentMethod) . '::METHOD') && $oPaymentMethod::METHOD !== '') {
            $data['method'] = $oPaymentMethod::METHOD;
        }
        $data['metadata'] = [
            'kBestellung' => $oBestellung->kBestellung,
            'kKunde' => $oBestellung->kKunde,
            'kKundengruppe' => Session::getCustomerGroup()->getID(),
            'cHash' => $oPaymentMethod->generateHash($oBestellung),
        ];

        return [$data, $hash];
    }

    /**
     * @param Bestellung $oBestellung
     * @param array $paymentOptions
     * @return MollieOrder|null
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
        $orderModel->setSynced(self::Plugin()->getConfig()->getValue('onlyPaid') !== 'on');

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
            if (!$oBestellung->Lieferadresse->cMail) {
                $oBestellung->Lieferadresse->cMail = $oBestellung->oRechnungsadresse->cMail;
            }
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

    /**
     * @param MollieOrder|Payment $order
     * @return bool
     * @throws Exception
     */
    public static function update($order): bool
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

    public static function sendReminders()
    {
        // TODO: Einstellung für Zeitraum

        $reminder = (int)self::Plugin()->getConfig()->getValue('reminder');

        if (!$reminder) {
            Shop::Container()->getDB()->executeQueryPrepared('UPDATE xplugin_ws5_mollie_orders SET dReminder = :dReminder WHERE dReminder IS NULL', [
                ':dReminder' => date('Y-m-d H:i:s')
            ], 3);
            return;
        }

        $remindables = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId FROM xplugin_ws5_mollie_orders WHERE dReminder IS NULL AND dCreated < NOW() - INTERVAL :d MINUTE AND cStatus IN ("created","open", "expired", "failed", "canceled")', [
            ':d' => $reminder
        ], 2);
        foreach ($remindables as $remindable) {
            self::sendReminder($remindable->kId);
        }
    }

    /**
     * @param $kID
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \SmartyException
     */
    public static function sendReminder($kID)
    {

        $order = OrderModel::loadByAttributes(['id' => $kID], Shop::Container()->getDB(), OrderModel::ON_NOTEXISTS_FAIL);

        $oBestellung = new Bestellung($order->getBestellung());
        $repayURL = Shop::getURL() . '/?m_pay=' . md5($order->getId() . '-' . $order->getBestellung());

        $data = new stdClass();
        $data->tkunde = new \JTL\Customer\Customer($oBestellung->kKunde);
        $data->Bestellung = $oBestellung;
        $data->PayURL = $repayURL;
        $data->Amount = Preise::getLocalizedPriceString($order->getAmount(), Currency::fromISO($order->getCurrency()), false);

        $mailer = Shop::Container()->get(Mailer::class);
        $mail = new Mail();
        $mail->createFromTemplateID('kPlugin_' . self::Plugin()->getID() . '_zahlungserinnerung', $data);

        $order->setReminder(date('Y-m-d H:i:s'));
        $order->save(['reminder']);

        if (!$mailer->send($mail)) {
            throw new Exception($mail->getError() . "\n" . print_r($order->rawArray(), 1));
        }
    }

}
