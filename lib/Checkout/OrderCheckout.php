<?php


namespace Plugin\ws5_mollie\lib\Checkout;


use Exception;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use stdClass;

class OrderCheckout extends AbstractCheckout
{

    protected $order;

    public function create(array $paymentOptions = [])
    {
        // TODO: Implement create() method.
    }


    public function getIncomingPayment(): ?stdClass
    {
        /** @var Payment $payment */
        foreach ($this->getOrder()->payments() as $payment) {
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

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        if (!$this->order) {
            try {
                $this->order = $this->getAPI()->getClient()->orders->get($this->getModel()->getOrderId(), ['embed' => 'payments']);
            } catch (Exception $e) {
                throw new \RuntimeException('Could not get Order');
            }
        }
        return $this->order;
    }

    public function loadRequest(array $options = []): AbstractCheckout
    {
        // TODO: Implement loadRequest() method.
    }

    /**
     * @return AbstractCheckout
     * @throws Exception
     */
    public function updateModel(): AbstractCheckout
    {
        parent::updateModel();
        $this->getModel()->setLocale($this->getOrder()->locale);
        $this->getModel()->setAmount($this->getOrder()->amount->value);
        $this->getModel()->setMethod($this->getOrder()->method);
        $this->getModel()->setCurrency($this->getOrder()->amount->currency);
        $this->getModel()->setOrderId($this->getOrder()->id);
        $this->getModel()->setSynced($this->getOrder()->status);
        $this->getModel()->setHash($this->getHash());
        $this->getModel()->setAmountRefunded($this->getOrder()->amountRefunded->value ?? 0);
        return $this;
    }

}