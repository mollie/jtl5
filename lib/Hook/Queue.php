<?php


namespace Plugin\ws5_mollie\lib\Hook;


use Exception;
use JTL\Alert\Alert;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use RuntimeException;


class Queue extends AbstractHook
{

    public static function bestellungInDB(array $args_arr): void
    {
        if (self::Plugin()->getConfig()->getValue('onlyPaid') === 'on'
            && array_key_exists('oBestellung', $args_arr)
            && AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kZahlungsart, true)) {

            $args_arr['oBestellung']->cAbgeholt = 'Y';
            Shop::Container()->getLogService()->info('Switch cAbgeholt for kBestellung: ' . print_r($args_arr['oBestellung']->kBestellung, 1));
        }
    }

    public static function xmlBestellStatus(array $args_arr): void
    {
        if (AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            self::saveToQueue(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS . ':' . (int)$args_arr['oBestellung']->kBestellung, [
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
        if (AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            self::saveToQueue(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO . ':' . $args_arr['oBestellung']->kBestellung, ['kBestellung' => $args_arr['oBestellung']->kBestellung]);
        }
    }

    public static function headPostGet(): void
    {
        if (array_key_exists('mollie', $_REQUEST) && (int)$_REQUEST['mollie'] === 1 && array_key_exists('id', $_REQUEST)) {
            self::saveToQueue($_REQUEST['id'], $_REQUEST['id'], 'webhook');
            exit();
        }
        if (array_key_exists('m_pay', $_REQUEST)) {
            try {
                $raw = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId, cOrderId FROM `xplugin_ws5_mollie_orders` WHERE dReminder IS NOT NULL AND MD5(CONCAT(kId, "-", kBestellung)) = :md5', [
                    ':md5' => $_REQUEST['m_pay']
                ], 1);

                if (!$raw) {
                    throw new RuntimeException(self::Plugin()->getLocalization()->getTranslation('errOrderNotFound'));
                }

                if (strpos($raw->cOrderId, 'tr_') === 0) {
                    $checkout = PaymentCheckout::fromID($raw->cOrderId);
                } else {
                    $checkout = OrderCheckout::fromID($raw->cOrderId);
                }
                $checkout->getMollie(true);
                $checkout->updateModel()->saveModel();

                if ($checkout->getBestellung()->dBezahltDatum !== null || in_array($checkout->getModel()->getStatus(), ['completed', 'paid', 'authorized', 'pending'])) {
                    throw new RuntimeException(self::Plugin()->getLocalization()->getTranslation('errAlreadyPaid'));
                }

                $options = [];
                if (self::Plugin()->getConfig()->getValue('resetMethod') !== 'on') {
                    $options['method'] = $checkout->getModel()->getMethod();
                }

                $mollie = $checkout->create($options); // Order::repayOrder($orderModel->getOrderId(), $options, $api);
                $url = $mollie->getCheckoutUrl();

                header('Location: ' . $url);
                exit();

            } catch (RuntimeException $e) {
                $alertHelper = Shop::Container()->getAlertService();
                $alertHelper->addAlert(Alert::TYPE_ERROR, $e->getMessage(), 'mollie_repay', ['dismissable' => true]);
            } catch (Exception $e) {
                Shop::Container()->getLogService()->addError('mollie:repay:error: ' . $e->getMessage() . "\n" . print_r($_REQUEST, 1));
            }
        }
    }

}