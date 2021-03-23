<?php


namespace Plugin\ws5_mollie\lib\Hook;


use Exception;
use JTL\Checkout\Bestellung;
use JTL\Shop;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Order;


class Queue extends AbstractHook
{

    public static function bestellungInDB(array $args_arr): void
    {
        if (self::Plugin()->getConfig()->getValue('onlyPaid') === 'on'
            && array_key_exists('oBestellung', $args_arr)
            && Order::isMollie((int)$args_arr['oBestellung']->kZahlungsart, true)) {

            $args_arr['oBestellung']->cAbgeholt = 'Y';
            Shop::Container()->getLogService()->info('Switch cAbgeholt for kBestellung: ' . print_r($args_arr['oBestellung']->kBestellung, 1));
        }
    }

    public static function xmlBestellStatus(array $args_arr): void
    {
        if (Order::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            self::saveToQueue(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [
                'kBestellung' => $args_arr['oBestellung']->kBestellung,
                'status' => (int)$args_arr['status']
            ]);
        }
    }

    protected static function saveToQueue($hook, $args_arr, $type = 'hook'): bool
    {
        $mQueue = QueueModel::newInstance(Shop::Container()->getDB());
        $mQueue->setType($type . ':' . $hook);
        $mQueue->setData(serialize($args_arr));
        $mQueue->setCreated(date('Y-m-d H:i:s'));
        try {
            return $mQueue->save();
        } catch (Exception $e) {
            Shop::Container()->getLogService()->error('mollie::saveToQueue: ' . $e->getMessage() . ' - ' . print_r($args_arr, 1));
            return false;
        }
    }

    public static function xmlBearbeiteStorno(array $args_arr): void
    {
        if (Order::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            self::saveToQueue(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, ['kBestellung' => $args_arr['oBestellung']->kBestellung]);
        }
    }

    public static function headPostGet(): void
    {
        if (array_key_exists('mollie', $_REQUEST) && (int)$_REQUEST['mollie'] === 1 && array_key_exists('id', $_REQUEST)) {
            self::saveToQueue($_REQUEST['id'], $_REQUEST['id'], 'webhook');
            exit();
        }
        if (array_key_exists('m_pay', $_REQUEST) && (int)$_REQUEST['m_pay']) {
            try {
                $orderModel = OrderModel::loadByAttributes(['id' => (int)$_REQUEST['m_pay']], Shop::Container()->getDB());
                $oBestellung = new Bestellung($orderModel->getBestellung(), true);

                if ($oBestellung->dBezahltDatum !== null || in_array($orderModel->getStatus(), ['completed', 'paid', 'authorized', 'pending'])) {
                    // TODO TRANSLATE
                    throw new Exception('Already paid!');
                }

                $api = MollieAPI::API($orderModel->getTest());

                if (strpos($orderModel->orderId, 'tr_') === 0) {
                    // Payment API

                    $payment = Order::createPayment($oBestellung);
                    header('Location: ' . $payment->getCheckoutUrl());
                    exit();


                } else {
                    // Order API
                    $mOrder = $api->orders->get($orderModel->getOrderId(), ['embed' => 'payments']);
                    if (in_array($mOrder->status, [OrderStatus::STATUS_COMPLETED, OrderStatus::STATUS_PAID, OrderStatus::STATUS_AUTHORIZED, OrderStatus::STATUS_PENDING], true)) {
                        throw new Exception('Already paid!');
                    }

                    if ($mOrder->payments()) {
                        /** @var Payment $payment */
                        foreach ($mOrder->payments() as $payment) {
                            if ($payment->status === PaymentStatus::STATUS_OPEN) {
                                header('Location: ' . $payment->getCheckoutUrl());
                                exit();
                            }
                        }
                    }

                    $newPayment = $api->orderPayments->createForId($orderModel->getOrderId(), []);
                    header('Location: ' . $newPayment->getCheckoutUrl());
                    exit();
                }

            } catch (Exception $e) {
                // TODO LOG
                throw $e;
            }
        }
    }

}