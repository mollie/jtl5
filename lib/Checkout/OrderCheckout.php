<?php


namespace Plugin\ws5_mollie\lib\Checkout;


use Exception;
use JTL\Session\Frontend;
use JTL\Shop;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Locale;
use Plugin\ws5_mollie\lib\Order\Address;
use Plugin\ws5_mollie\lib\Order\Amount;
use Plugin\ws5_mollie\lib\Order\OrderLine as WSOrderLine;
use RuntimeException;
use stdClass;

class OrderCheckout extends AbstractCheckout
{

    protected $order;

    public function create(array $paymentOptions = []): Order
    {
        if ($this->getModel()->orderId) {
            try {
                $this->order = $this->getAPI()->getClient()->orders->get($this->getModel()->orderId, ['embed' => 'payments']);
                if (in_array($this->order->status, [OrderStatus::STATUS_COMPLETED, OrderStatus::STATUS_PAID, OrderStatus::STATUS_AUTHORIZED, OrderStatus::STATUS_PENDING], true)) {
                    throw new RuntimeException(self::Plugin()->getLocalization()->getTranslation('errAlreadyPaid'));
                }
                if ($this->order->status === OrderStatus::STATUS_CREATED) {
                    // TODO REPAY
                    if($this->order->payments()){
                        /** @var Payment $payment */
                        foreach ($this->order->payments() as $payment){
                            if($payment->status === PaymentStatus::STATUS_OPEN){
                                return $this->order;
                            }
                        }
                    }
                    $payment = $this->getAPI()->getClient()->orderPayments->createForId($this->getModel()->getOrderId(), $paymentOptions);
                    $this->getModel()->setTransactionId($payment->id);
                    $this->saveModel();
                    return $this->getMollie(true);
                }
            } catch (Exception $e) {
                $this->getPaymentMethod()->doLog(sprintf("Letzte Order '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->orderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $req = $this->loadRequest($paymentOptions)->getRequestData();
            $this->order = $this->getAPI()->getClient()->orders->create($req);
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->getPaymentMethod()->doLog(sprintf("Neue Order '%s' konnte nicht erstellt werden: %s.", $this->oBestellung->cBestellNr, $e->getMessage()), LOGLEVEL_ERROR);
            // TODO: Translate?
            throw new \RuntimeException('Order konnte nicht angelegt werden.');
        }
        return $this->order;
    }

    /**
     * @param array $options
     * @return AbstractCheckout
     * @throws Exception
     */
    public function loadRequest(array $options = []): AbstractCheckout
    {

        $this->setRequestData('locale', Locale::getLocale(Frontend::get('cISOSprache', 'ger'), Frontend::getCustomer()->cLand))
            ->setRequestData('amount', new Amount($this->oBestellung->fGesamtsumme, $this->oBestellung->Waehrung, true, true))
            ->setRequestData('orderNumber', $this->oBestellung->cBestellNr)
            ->setRequestData('metadata', [
                'kBestellung' => $this->oBestellung->kBestellung,
                'kKunde' => $this->oBestellung->kKunde,
                'kKundengruppe' => Frontend::getCustomerGroup()->getID(),
                'cHash' => $this->getHash(),
            ])
            ->setRequestData('redirectUrl', $this->getPaymentMethod()->getReturnURL($this->oBestellung))
            ->setRequestData('webhookUrl', Shop::getURL(true) . '/?mollie=1');

        if (defined(get_class($this->getPaymentMethod()) . '::METHOD') && $this->getPaymentMethod()::METHOD !== '') {
            $this->setRequestData('method', $this->getPaymentMethod()::METHOD);
        }

        $this->setRequestData('billingAddress', Address::factory($this->oBestellung->oRechnungsadresse));
        if ($this->oBestellung->Lieferadresse !== null) {
            if (!$this->oBestellung->Lieferadresse->cMail) {
                $this->oBestellung->Lieferadresse->cMail = $this->oBestellung->oRechnungsadresse->cMail;
            }
            $this->setRequestData('shippingAddress', Address::factory($this->oBestellung->Lieferadresse));
        }

        if (
            !empty(Frontend::getCustomer()->dGeburtstag)
            && Frontend::getCustomer()->dGeburtstag !== '0000-00-00'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim(Frontend::getCustomer()->dGeburtstag))
        ) {
            $this->setRequestData('consumerDateOfBirth', trim(Frontend::getCustomer()->dGeburtstag));
        }

        $lines = [];
        foreach ($this->oBestellung->Positionen as $oPosition) {
            $lines[] = WSOrderLine::factory($oPosition, $this->oBestellung->Waehrung);
        }

        if ($this->oBestellung->GuthabenNutzen && $this->oBestellung->fGuthaben > 0) {
            $lines[] = WSOrderLine::getCredit($this->oBestellung);
        }

        if ($comp = WSOrderLine::getRoundingCompensation($lines, $this->getRequestData()['amount'], $this->oBestellung->Waehrung)) {
            $lines[] = $comp;
        }
        $this->setRequestData('lines', $lines);

        $this->setRequestData('payment', $options);

        return $this;

    }

    /**
     * @return AbstractCheckout
     * @throws Exception
     */
    public function updateModel(): AbstractCheckout
    {
        parent::updateModel();
        $this->getModel()->setStatus($this->getMollie()->status);
        $this->getModel()->setHash($this->getHash());
        $this->getModel()->setAmountRefunded($this->getMollie()->amountRefunded->value ?? 0);
        return $this;
    }

    /**
     * @return Order
     */
    public function getMollie($force = false): ?Order
    {
        if ($force || (!$this->order && $this->getModel()->getOrderId())) {
            try {
                $this->order = $this->getAPI()->getClient()->orders->get($this->getModel()->getOrderId(), ['embed' => 'payments,shipments,refunds']);
            } catch (Exception $e) {
                throw new \RuntimeException('Could not get Order');
            }
        }
        return $this->order;
    }

    public function getIncomingPayment(): ?stdClass
    {
        /** @var Payment $payment */
        foreach ($this->getMollie()->payments() as $payment) {
            if (in_array($payment->status,
                [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
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

}