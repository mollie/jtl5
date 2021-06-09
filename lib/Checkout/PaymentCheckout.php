<?php


namespace Plugin\ws5_mollie\lib\Checkout;


use Exception;
use JTL\Session\Frontend;
use JTL\Shop;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Locale;
use Plugin\ws5_mollie\lib\Order\Amount;
use stdClass;

class PaymentCheckout extends AbstractCheckout
{

    protected $payment;

    /**
     * @param array $paymentOptions
     * @return Payment
     * @throws Exception
     */
    public function create(array $paymentOptions = []): Payment
    {

        if ($this->getModel()->orderId) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($this->getModel()->orderId);
                if ($this->payment->status === PaymentStatus::STATUS_OPEN) {
                    $this->updateModel()->updateModel();
                    return $this->payment;
                }
            } catch (Exception $e) {
                $this->getPaymentMethod()->doLog(sprintf("PaymentCheckout::create: Letzte Transaktion '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->orderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $req = $this->loadRequest($paymentOptions)->getRequestData();
            $this->payment = $this->getAPI()->getClient()->payments->create($req);
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->getPaymentMethod()->doLog(sprintf("PaymentCheckout::create: Neue Transaktion '%s' konnte nicht erstellt werden: %s.\n%s", $this->oBestellung->cBestellNr, $e->getMessage(), json_encode($req)), LOGLEVEL_ERROR);
            throw new \RuntimeException(sprintf('Mollie-Payment \'%s\' konnte nicht geladen werden: %s', $this->getModel()->getOrderId(), $e->getMessage()));
        }
        return $this->payment;
    }

    /**
     * @return AbstractCheckout
     * @throws Exception
     */
    public function updateModel(): AbstractCheckout
    {
        parent::updateModel();
        $this->getModel()->setHash($this->getHash());
        $this->getModel()->setAmountRefunded($this->getMollie()->amountRefunded->value ?? 0);
        return $this;
    }

    /**
     * @return Payment
     */
    public function getMollie($force = false): ?Payment
    {
        if ($force || (!$this->payment && $this->getModel()->getOrderId())) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($this->getModel()->getOrderId(), ['embed' => 'refunds']);
            } catch (Exception $e) {
                throw new \RuntimeException('Mollie-Payment konnte nicht geladen werden: ' . $e->getMessage());
            }
        }
        return $this->payment;
    }

    /**
     * @param array $options
     * @return $this
     * @throws Exception
     */
    public function loadRequest(array &$options = []): self
    {
        $this->setRequestData('amount', new Amount($this->oBestellung->fGesamtsumme, $this->oBestellung->Waehrung, true, true))
            ->setRequestData('description', 'Order ' . $this->oBestellung->cBestellNr)
            ->setRequestData('redirectUrl', $this->getPaymentMethod()->getReturnURL($this->oBestellung))
            ->setRequestData('webhookUrl', Shop::getURL(true) . '/?mollie=1')
            ->setRequestData('locale', Locale::getLocale(Frontend::get('cISOSprache', 'ger'), Frontend::getCustomer()->cLand))
            ->setRequestData('metadata', [
                'kBestellung' => $this->oBestellung->kBestellung,
                'kKunde' => $this->oBestellung->kKunde,
                'kKundengruppe' => Frontend::getCustomerGroup()->getID(),
                'cHash' => $this->getHash(),
            ]);
        /** @noinspection NotOptimalIfConditionsInspection */
        if (defined(get_class($this->getPaymentMethod()) . '::METHOD') && $this->getPaymentMethod()::METHOD !== ''
            && (self::Plugin()->getConfig()->getValue('resetMethod') !== 'on' || !$this->getMollie())) {
            $this->setRequestData('method', $this->getPaymentMethod()::METHOD);
        }
        foreach ($options as $key => $value) {
            $this->setRequestData($key, $value);
        }

        return $this;
    }

    public function getIncomingPayment(): ?stdClass
    {
        if (in_array($this->getMollie()->status, [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
            $data = [];
            $data['fBetrag'] = (float)$this->getMollie()->amount->value;
            $data['cISO'] = $this->getMollie()->amount->currency;
            $data['cZahler'] = $this->getMollie()->details->paypalPayerId ?? $this->getMollie()->customerId;
            $data['cHinweis'] = $this->getMollie()->details->paypalReference ?? $this->getMollie()->id;
            return (object)$data;
        }
        return null;
    }

    public function cancelOrRefund(): string
    {
        if ((int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_STORNO) {
            if ($this->getMollie()->isCancelable) {
                $res = $this->getAPI()->getClient()->payments->cancel($this->getMollie()->id);
                return 'Payment cancelled, Status: ' . $res->status;
            }
            $res = $this->getAPI()->getClient()->payments->refund($this->getMollie(), ['amount' => $this->getMollie()->amount]);
            return "Payment Refund initiiert, Status: " . $res->status;
        }
        throw new Exception('Bestellung ist derzeit nicht storniert, Status: ' . $this->getBestellung()->cStatus);
    }
}
