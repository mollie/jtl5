<?php


namespace Plugin\ws5_mollie\lib\Checkout;


use Exception;
use JTL\Shopsetting;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Shop;
use stdClass;

/**
 * Class PaymentCheckout
 * @package Plugin\ws5_mollie\lib\Checkout
 * @property string $description
 * @property string $customerId
 *
 */
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
                $this->payment = $this->getAPI()->getClient()->payments->get($this->getModel()->cOrderId);
                if ($this->payment->status === PaymentStatus::STATUS_OPEN) {
                    $this->updateModel()->updateModel();
                    return $this->payment;
                }
            } catch (Exception $e) {
                $this->getPaymentMethod()->doLog(sprintf("PaymentCheckout::create: Letzte Transaktion '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->cOrderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $req = $this->loadRequest($paymentOptions)->jsonSerialize();
            $this->payment = $this->getAPI()->getClient()->payments->create($req);
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->getPaymentMethod()->doLog(sprintf("PaymentCheckout::create: Neue Transaktion '%s' konnte nicht erstellt werden: %s.\n%s", $this->oBestellung->cBestellNr, $e->getMessage(), json_encode($req)), LOGLEVEL_ERROR);
            throw new \RuntimeException(sprintf('Mollie-Payment \'%s\' konnte nicht geladen werden: %s', $this->getModel()->cOrderId, $e->getMessage()));
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
        $this->getModel()->cHash = $this->getHash();
        $this->getModel()->fAmountRefunded = $this->getMollie()->amountRefunded->value ?? 0;
        return $this;
    }

    /**
     * @return Payment
     * @throws Exception
     */
    public function getMollie($force = false): ?Payment
    {
        if ($force || (!$this->payment && $this->getModel()->cOrderId)) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($this->getModel()->cOrderId, ['embed' => 'refunds']);
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
    public function loadRequest(array &$options = [])
    {

        parent::loadRequest($options);

        $this->description = $this->getDescription();

        foreach ($options as $key => $value) {
            $this->$key = $value;
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

    /**
     * @param \Mollie\Api\Resources\Order|Payment $model
     * @return $this|PaymentCheckout
     */
    protected function setMollie($model)
    {
        $this->payment = $model;
        return $this;
    }

    protected function updateOrderNumber()
    {
        try {
            if ($this->getMollie()) {
                $this->getMollie()->description = $this->getDescription();
                $this->getMollie()->webhookUrl = Shop::getURL() . '/?mollie=1';
                $this->getMollie()->update();
            }
        } catch (Exception $e) {
            $this->Log('OrderCheckout::updateOrderNumber:' . $e->getMessage(), LOGLEVEL_ERROR);
        }
        return $this;
    }

    /**
     * @return array|string|string[]
     * @throws Exception
     */
    public function getDescription()
    {
        $descTemplate = trim(self::Plugin()->getConfig()->getValue('paymentDescTpl')) ?: "Order {orderNumber}";
        $oKunde = $this->getBestellung()->oKunde ?: $_SESSION['Kunde'];
        return str_replace([
            '{orderNumber}',
            '{storeName}',
            '{customer.firstname}',
            '{customer.lastname}',
            '{customer.company}',
        ], [
            $this->getBestellung()->cBestellNr,
            Shopsetting::getInstance()->getValue(CONF_GLOBAL, 'global_shopname'),  //Shop::getSettings([CONF_GLOBAL])['global']['global_shopname'],
            $oKunde->cVorname,
            $oKunde->cNachname,
            $oKunde->cFirma
        ], $descTemplate);
    }
}
