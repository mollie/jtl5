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
                $this->getPaymentMethod()->doLog(sprintf("Letzte Transaktion '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->orderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $req = $this->loadRequest($paymentOptions)->getRequestData();
            $this->payment = $this->getAPI()->getClient()->payments->create($req);
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->getPaymentMethod()->doLog(sprintf("Neue Transaktion '%s' konnte nicht erstellt werden: %s.", $this->oBestellung->cBestellNr, $e->getMessage()), LOGLEVEL_ERROR);
            // TODO: Translate?
            throw new \RuntimeException('Transaktion konnte nicht angelegt werden.');
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
    public function getMollie($force = false): Payment
    {
        if ($force || !$this->payment) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($this->getModel()->getOrderId(), ['embed' => 'refunds']);
            } catch (Exception $e) {
                throw new \RuntimeException('Could not get Payment');
            }
        }
        return $this->payment;
    }

    public function loadRequest($options = []): AbstractCheckout
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
                'cHash' => $this->getPaymentMethod()->generateHash($this->oBestellung),
            ]);
        /** @noinspection NotOptimalIfConditionsInspection */
        if (defined(get_class($this->getPaymentMethod()) . '::METHOD') && $this->getPaymentMethod()::METHOD !== '') {
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
}