<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Checkout;

use Exception;
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
     * @throws Exception
     * @return Payment
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
                $this->Log(sprintf("PaymentCheckout::create: Letzte Transaktion '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->cOrderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $req           = $this->loadRequest($paymentOptions)->jsonSerialize();
            $this->payment = $this->getAPI()->getClient()->payments->create($req);
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->Log(sprintf("PaymentCheckout::create: Neue Transaktion '%s' konnte nicht erstellt werden: %s.\n%s", $this->oBestellung->cBestellNr, $e->getMessage(), json_encode($req)), LOGLEVEL_ERROR);

            throw new \RuntimeException(sprintf('Mollie-Payment \'%s\' konnte nicht geladen werden: %s', $this->getModel()->cOrderId, $e->getMessage()));
        }

        return $this->payment;
    }

    /**
     * @throws Exception
     *
     * @return static
     */
    public function updateModel(): AbstractCheckout
    {
        parent::updateModel();
        $this->getModel()->cHash           = $this->getHash();
        $this->getModel()->fAmountRefunded = $this->getMollie()->amountRefunded->value ?? 0;

        return $this;
    }

    /**
     * @param mixed $force
     * @throws Exception
     * @throws Exception
     * @return Payment
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
     * @throws Exception
     * @return $this
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

    /**
     * @throws Exception
     * @return null|stdClass
     */
    public function getIncomingPayment(): ?stdClass
    {
        if (in_array($this->getMollie()->status, [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
            $data             = [];
            $data['fBetrag']  = (float)$this->getMollie()->amount->value;
            $data['cISO']     = $this->getMollie()->amount->currency;
            $data['cZahler']  = $this->getMollie()->details->paypalPayerId ?? $this->getMollie()->customerId;
            $data['cHinweis'] = $this->getMollie()->details->paypalReference ?? $this->getMollie()->id;

            return (object)$data;
        }

        return null;
    }

    /**
     * @return string
     */
    public function cancelOrRefund(): string
    {
        if ((int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_STORNO) {
            if ($this->getMollie()->isCancelable) {
                $res = $this->getAPI()->getClient()->payments->cancel($this->getMollie()->id);

                return 'Payment cancelled, Status: ' . $res->status;
            }
            $res = $this->getAPI()->getClient()->payments->refund($this->getMollie(), ['amount' => $this->getMollie()->amount]);

            return 'Payment Refund initiiert, Status: ' . $res->status;
        }

        throw new Exception('Bestellung ist derzeit nicht storniert, Status: ' . $this->getBestellung()->cStatus);
    }

    /**
     * @param \Mollie\Api\Resources\Order|Payment $model
     *
     * @return static
     */
    protected function setMollie($model)
    {
        $this->payment = $model;

        return $this;
    }

    /**
     * @return static
     */
    protected function updateOrderNumber()
    {
        try {
            if ($this->getMollie()) {
                $this->getMollie()->description = $this->getDescription();
                $this->getMollie()->webhookUrl  = Shop::getURL() . '/?mollie=1';
                $this->getMollie()->update();
            }
        } catch (Exception $e) {
            $this->Log('OrderCheckout::updateOrderNumber:' . $e->getMessage(), LOGLEVEL_ERROR);
        }

        return $this;
    }
}
