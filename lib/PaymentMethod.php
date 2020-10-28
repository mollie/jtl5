<?php

/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib;

use Exception;
use JTL\Alert\Alert;
use JTL\Checkout\Bestellung;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Model\DataModel;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\Payment\MethodInterface;
use JTL\Plugin\Plugin;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use RuntimeException;
use Session;
use Shop;
use stdClass;

class PaymentMethod extends Method
{

    public const ALLOW_PAYMENT_BEFORE_ORDER = false;

    public const METHOD = '';
    /**
     * @var string
     */
    protected $pluginID;
    /**
     * @var Plugin
     */
    protected $oPlugin;

    /**
     * @param int $nAgainCheckout
     * @return $this|Method|MethodInterface|PaymentMethod
     */
    public function init($nAgainCheckout = 0)
    {
        parent::init($nAgainCheckout);

        $this->pluginID = PluginHelper::getIDByModuleID($this->moduleID);
        $this->oPlugin = PluginHelper::getLoaderByPluginID($this->pluginID)->init($this->pluginID);

        return $this;
    }

    public function canPayAgain(): bool
    {
        return true;
    }

    /**
     * @param array $args_arr
     * @return bool
     */
    public function isValidIntern(array $args_arr = []): bool
    {
        return $this->duringCheckout
            ? static::ALLOW_PAYMENT_BEFORE_ORDER && parent::isValidIntern($args_arr)
            : parent::isValidIntern($args_arr);
    }

    public function isSelectable(): bool
    {
        $selectable = true;
        return $selectable && parent::isSelectable();
    }

    /**
     * @param Bestellung $order
     */
    public function preparePaymentProcess(Bestellung $order): void
    {
        parent::preparePaymentProcess($order);
        try {
            $payable = (float)$order->fGesamtsumme > 0;
            if (!$payable) {
                if ($this->duringCheckout) {
                    // TODO: Derzeit deaktiviert
                } else {
                    return;
                }
            }

            $customerID = null;
            if ((int)Session::getCustomer()->nRegistriert) {
                $customerID = Customer::createOrUpdate(Session::getCustomer());
            }


            if ($this->duringCheckout) {
                // TODO: derzeit deaktiviert
            } else {
                $mollieOrder = Order::createOrder($order, $customerID);

                if ($mollieOrder) {

                    $this->handleNotification($order, $mollieOrder->metadata->cHash, ['id' => $mollieOrder->id]);

                    if (!headers_sent()) {
                        header('Location: ' . $mollieOrder->getCheckoutUrl());
                    }
                    Shop::Smarty()->assign('redirect', $mollieOrder->getCheckoutUrl());
                } else {
                    throw new RuntimeException('Order konnte bei mollie nicht erstellt werden!');
                }

            }
        } catch (Exception $e) {
            Shop::Container()->getAlertService()->addAlert(
                Alert::TYPE_ERROR,
                $e->getMessage(),
                'paymentFailed'
            );
        }
    }

    /**
     * @param Bestellung $order
     * @param string $hash
     * @param array $args
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function handleNotification(Bestellung $order, string $hash, array $args): void
    {
        parent::handleNotification($order, $hash, $args);

        try {

            //throw new \RuntimeException('FUCK!');

            $orderId = $args['id'];

            $orderModel = OrderModel::loadByAttributes(
                ['orderId' => $orderId, 'hash' => $hash],
                Shop::Container()->getDB(),
                DataModel::ON_NOTEXISTS_NEW);

            $mOrder = MollieAPI::API($orderModel->getTest())->orders->get($orderId, ['embed' => 'payments']);
            $payment = null;
            /** @var Payment $payment */
            /** @var Payment $_payment */
            $payValue = 0.0;
            foreach ($mOrder->payments() as $_payment) {
                if (in_array($_payment->status,
                    [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
                    $payment = $_payment;
                    $payValue += (float)$_payment->amount->value;
                }
            }

            $orderModel->setBestellung($order->kBestellung);
            $orderModel->setModified(date('Y-m-d H:i:s'));
            $orderModel->setStatus($mOrder->status);
            $orderModel->setTransactionId($payment->id ?? '');
            $orderModel->setThirdId($payment->details->paypalReference ?? '');

            /** @noinspection NotOptimalIfConditionsInspection */
            if ($orderModel->save() && $order->dBezahltDatum === null && $payment) {

                $this->addIncomingPayment($order, (object)[
                    'fBetrag' => $payment->amount->value,
                    'cISO' => $payment->amount->currency,
                    'cZahler' => $payment->details->paypalPayerId ?? $payment->customerId,
                    'cHinweis' => $payment->details->paypalReference ?? $mOrder->id,
                ]);

                // If totally paid, mark as paid, make fetchable by WAWI and delete Hash
                if ($payValue >= $order->fGesamtsumme) {
                    $this->setOrderStatusToPaid($order);
                    self::makeFetchable($order, $orderModel);
                    $this->deletePaymentHash($hash);
                }
            }
        } catch (Exception $e) {
            Shop::Container()->getLogService()->addCritical($e->getMessage(), $_REQUEST);
        }

    }

    /**
     * @param Bestellung $order
     * @param OrderModel $orderModel
     * @return bool
     * @throws Exception
     */
    public static function makeFetchable(Bestellung $order, OrderModel $orderModel): bool
    {

        if ($order->cAbgeholt === 'Y' && $orderModel->getSynced() === false) {
            $_upd = new stdClass();
            $_upd->cAbgeholt = 'N';
            if (\JTL\Shop::Container()->getDB()->update('tbestellung', 'kBestellung', (int)$order->kBestellung, $_upd)) {
                $orderModel->setSynced(true);
                return $orderModel->save();
            }
        }
        return false;
    }


}
