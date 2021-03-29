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
use JTL\Mail\Mail\Mail;
use JTL\Mail\Mailer;
use JTL\Model\DataModel;
use JTL\Plugin\Payment\LegacyMethod;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
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
                $api = new MollieAPI($orderModel->test);
                $order = $api->getClient()->orders->get($orderModel->getOrderId(), ['embed' => 'payments']);
                if ($order->status === OrderStatus::STATUS_CREATED) {
                    return self::repayOrder($order->id, [], $api->getClient());
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

        $order = $api->getClient()->orders->create($data->toArray());
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
     * @param $orderId
     * @param array $options
     * @param MollieApiClient|null $api
     * @return MollieOrder
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public static function repayOrder($orderId, array $options = [], MollieApiClient $api = null): MollieOrder
    {
        if (!$api) {
            $api = MollieAPI::API(MollieAPI::getMode());
        }
        $order = $api->orders->get($orderId, ['embed' => 'payments']);
        if (in_array($order->status, [OrderStatus::STATUS_COMPLETED, OrderStatus::STATUS_PAID, OrderStatus::STATUS_AUTHORIZED, OrderStatus::STATUS_PENDING], true)) {
            throw new RuntimeException(self::Plugin()->getLocalization()->getTranslation('errAlreadyPaid'));
        }
        if ($order->payments()) {
            /** @var Payment $payment */
            foreach ($order->payments() as $payment) {
                if ($payment->status === PaymentStatus::STATUS_OPEN) {
                    return $order;
                }
            }
        }

        $payment = $api->orderPayments->createForId($order->id, $options);

        Shop::Container()->getDB()->executeQueryPrepared("UPDATE xplugin_ws5_mollie_orders SET cTransactionId = :paymentId WHERE cOrderId = :orderId;", [
            ':orderId' => $payment->orderId,
            ':paymentId' => $payment->id
        ], 3);

        return $api->orders->get($order->id, ['embed' => 'payments']);
    }

    /**
     * @param Bestellung $oBestellung
     * @return array
     * @throws Exception
     */
    public static function factory(Bestellung $oBestellung): array
    {

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

        $orderModel->setStatus($order->status);
        $orderModel->setLocale($order->locale);
        $orderModel->setAmount($order->amount->value);
        $orderModel->setMethod($order->method);
        $orderModel->setCurrency($order->amount->currency);
        $orderModel->setOrderId($order->id);
        if ($order->amountRefunded) {
            $orderModel->setAmountRefunded($order->amountRefunded->value);
        }

        return $orderModel->save();

    }

    public static function sendReminders()
    {
        $reminder = (int)self::Plugin()->getConfig()->getValue('reminder');

        if (!$reminder) {
            Shop::Container()->getDB()->executeQueryPrepared('UPDATE xplugin_ws5_mollie_orders SET dReminder = :dReminder WHERE dReminder IS NULL', [
                ':dReminder' => date('Y-m-d H:i:s')
            ], 3);
            return;
        }

        $remindables = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId FROM xplugin_ws5_mollie_orders WHERE dReminder IS NULL AND dCreated < NOW() - INTERVAL :d HOUR AND cStatus IN ("created","open", "expired", "failed", "canceled")', [
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
    public static function sendReminder($kID): bool
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
        return true;
    }

}
