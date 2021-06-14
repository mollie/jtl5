<?php


namespace Plugin\ws5_mollie\lib\Checkout;


use DateTime;
use DateTimeZone;
use Exception;
use JTL\Session\Frontend;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Order\Address;
use Plugin\ws5_mollie\lib\Order\OrderLine as WSOrderLine;
use RuntimeException;
use Shop;
use stdClass;

/**
 * Class OrderCheckout
 * @package Plugin\ws5_mollie\lib\Checkout
 *
 * @property string $orderNumber
 * @property Address $billingAddress
 * @property Address|null $shippingAddress
 * @property string|null $consumerDateOfBirth
 * @property WSOrderLine[] $lines
 * @property string|null $expiresAt
 * @property array|null $payment
 */
class OrderCheckout extends AbstractCheckout
{

    /** @var Order */
    protected $order;

    /** @var Payment */
    protected $mollie;

    /**
     * @param array $paymentOptions
     * @return Order
     * @throws Exception
     */
    public function create(array $paymentOptions = []): Order
    {
        if ($this->getModel()->orderId) {
            try {
                $this->order = $this->getAPI()->getClient()->orders->get($this->getModel()->orderId, ['embed' => 'payments']);
                if (in_array($this->order->status, [OrderStatus::STATUS_COMPLETED, OrderStatus::STATUS_PAID, OrderStatus::STATUS_AUTHORIZED, OrderStatus::STATUS_PENDING], true)) {
                    throw new RuntimeException(self::Plugin()->getLocalization()->getTranslation('errAlreadyPaid'));
                }
                if ($this->order->status === OrderStatus::STATUS_CREATED) {
                    if ($this->order->payments()) {
                        /** @var Payment $payment */
                        foreach ($this->order->payments() as $payment) {
                            if ($payment->status === PaymentStatus::STATUS_OPEN) {
                                $this->mollie = $payment;
                                break;
                            }
                        }
                    }
                    if (!$this->mollie) {
                        $this->mollie = $this->getAPI()->getClient()->orderPayments->createForId($this->getModel()->getOrderId(), $paymentOptions);
                    }
                    $this->updateModel()->saveModel();
                    return $this->getMollie(true);
                }
            } catch (Exception $e) {
                $this->getPaymentMethod()->doLog(sprintf("OrderCheckout::create: Letzte Order '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->orderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $this->order = $this->getAPI()->getClient()->orders->create($this->loadRequest($paymentOptions)->jsonSerialize());
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->getPaymentMethod()->doLog(sprintf("OrderCheckout::create: Neue Order '%s' konnte nicht erstellt werden: %s.", $this->oBestellung->cBestellNr, $e->getMessage()), LOGLEVEL_ERROR);
            throw new RuntimeException('Order konnte nicht angelegt werden.');
        }
        return $this->order;
    }

    /**
     * @return AbstractCheckout
     * @throws Exception
     */
    public function updateModel(): AbstractCheckout
    {
        parent::updateModel();
        if (!$this->mollie && $this->getMollie() && $this->getMollie()->payments()) {
            /** @var Payment $payment */
            foreach ($this->getMollie()->payments() as $payment) {
                if (in_array($payment->status, [PaymentStatus::STATUS_OPEN, PaymentStatus::STATUS_PENDING, PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
                    $this->mollie = $payment;
                    break;
                }
            }
        }
        if ($this->mollie) {
            $this->getModel()->setTransactionId($this->mollie->id);
        }
        $this->getModel()->setStatus($this->getMollie()->status);
        $this->getModel()->setHash($this->getHash());
        $this->getModel()->setAmountRefunded($this->getMollie()->amountRefunded->value ?? 0);
        return $this;
    }

    /**
     * @param bool $force
     * @return Order
     * @throws Exception
     */
    public function getMollie($force = false): ?Order
    {
        if ($force || (!$this->order && $this->getModel()->getOrderId())) {
            try {
                $this->order = $this->getAPI()->getClient()->orders->get($this->getModel()->getOrderId(), ['embed' => 'payments,shipments,refunds']);
            } catch (Exception $e) {
                throw new RuntimeException(sprintf('Mollie-Order \'%s\' konnte nicht geladen werden: %s', $this->getModel()->getOrderId(), $e->getMessage()));
            }
        }
        return $this->order;
    }

    /**
     * @param Order|Payment $model
     * @return $this|OrderCheckout
     */
    protected function setMollie($model)
    {
        $this->order = $model;
        return $this;
    }

    /**
     * @param array $options
     * @return $this
     * @throws Exception
     */
    public function loadRequest(array &$options = [])
    {

        parent::loadRequest($options);

        $this->orderNumber = $this->getBestellung()->cBestellNr;
        $this->billingAddress = Address::factory($this->getBestellung()->oRechnungsadresse);
        if ($this->getBestellung()->Lieferadresse !== null) {
            if (!$this->getBestellung()->Lieferadresse->cMail) {
                $this->getBestellung()->Lieferadresse->cMail = $this->getBestellung()->oRechnungsadresse->cMail;
            }
            $this->shippingAddress = Address::factory($this->getBestellung()->Lieferadresse);
        }

        if (
            !empty(Frontend::getCustomer()->dGeburtstag)
            && Frontend::getCustomer()->dGeburtstag !== '0000-00-00'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim(Frontend::getCustomer()->dGeburtstag))
        ) {
            $this->consumerDateOfBirth = trim(Frontend::getCustomer()->dGeburtstag);
        }

        $lines = [];
        foreach ($this->getBestellung()->Positionen as $oPosition) {
            $lines[] = WSOrderLine::factory($oPosition, $this->getBestellung()->Waehrung);
        }

        if ($this->getBestellung()->GuthabenNutzen && $this->getBestellung()->fGuthaben > 0) {
            $lines[] = WSOrderLine::getCredit($this->getBestellung());
        }

        if ($comp = WSOrderLine::getRoundingCompensation($lines, $this->amount, $this->getBestellung()->Waehrung)) {
            $lines[] = $comp;
        }
        $this->lines = $lines;

        if (($dueDays = (int)self::Plugin()->getConfig()->getValue($this->getPaymentMethod()->moduleID . '_dueDays')) && $dueDays > 0) {
            $max = $this->method && strpos($this->method, 'klarna') !== false ? 28 : 100;
            $date = new DateTime(sprintf("+%d DAYS", min($dueDays, $max)), new DateTimeZone('UTC'));
            $this->expiresAt = $date->format('Y-m-d');
        }

        $this->payment = $options;

        return $this;

    }

    /**
     * @return stdClass|null
     * @throws Exception
     */
    public function getIncomingPayment(): ?stdClass
    {
        /** @var Payment $payment */
        foreach ($this->getMollie()->payments() as $payment) {
            if (in_array($payment->status,
                [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
                $this->mollie = $payment;
                return (object)[
                    'fBetrag' => (float)$payment->amount->value,
                    'cISO' => $payment->amount->currency,
                    'cZahler' => $payment->details->paypalPayerId ?? $payment->customerId,
                    'cHinweis' => $payment->details->paypalReference ?? $payment->id,
                ];
            }
        }
        return null;
    }

    /**
     * @return string
     * @throws ApiException
     */
    public function cancelOrRefund(): string
    {
        if ((int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_STORNO) {
            if ($this->getMollie()->isCancelable) {
                $res = $this->getMollie()->cancel();
                return 'Order cancelled, Status: ' . $res->status;
            }
            $res = $this->getMollie()->refundAll();
            return "Order Refund initiiert, Status: " . $res->status;
        }
        throw new Exception('Bestellung ist derzeit nicht storniert, Status: ' . $this->getBestellung()->cStatus);
    }

    protected function updateOrderNumber()
    {
        try {
            if ($this->getMollie()) {
                $this->getMollie()->orderNumber = $this->getBestellung()->cBestellNr;
                $this->getMollie()->webhookUrl = Shop::getURL() . '/?mollie=1';
                $this->getMollie()->update();
            }
        } catch (Exception $e) {
            $this->Log('OrderCheckout::updateOrderNumber:' . $e->getMessage(), LOGLEVEL_ERROR);
        }
        return $this;
    }

}
