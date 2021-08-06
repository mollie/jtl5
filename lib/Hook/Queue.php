<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use Exception;
use JTL\Alert\Alert;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use RuntimeException;

class Queue extends \WS\JTL5\Hook\AbstractHook
{
    public static function bestellungInDB(array $args_arr): void
    {
        if (
            self::Plugin('ws5_mollie')->getConfig()->getValue('onlyPaid') === 'on'
            && array_key_exists('oBestellung', $args_arr)
            && AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kZahlungsart, true)
        ) {
            $args_arr['oBestellung']->cAbgeholt = 'Y';
            Shop::Container()->getLogService()->info('Switch cAbgeholt for kBestellung: ' . print_r($args_arr['oBestellung']->kBestellung, 1));
        }
    }

    public static function xmlBestellStatus(array $args_arr): void
    {
        if (AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            QueueModel::saveToQueue(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS . ':' . (int)$args_arr['oBestellung']->kBestellung, [
                'kBestellung' => $args_arr['oBestellung']->kBestellung,
                'status'      => (int)$args_arr['status']
            ]);
        }
    }

    public static function xmlBearbeiteStorno(array $args_arr): void
    {
        if (AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            QueueModel::saveToQueue(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO . ':' . $args_arr['oBestellung']->kBestellung, ['kBestellung' => $args_arr['oBestellung']->kBestellung]);
        }
    }

    public static function headPostGet(): void
    {
        if (array_key_exists('mollie', $_REQUEST) && (int)$_REQUEST['mollie'] === 1 && array_key_exists('id', $_REQUEST)) {
            if (array_key_exists('hash', $_REQUEST) && $hash = trim(\StringHandler::htmlentities(\StringHandler::filterXSS($_REQUEST['hash'])), '_')) {
                AbstractCheckout::finalizeOrder($hash, $_REQUEST['id'], array_key_exists('test', $_REQUEST));
            } else {
                QueueModel::saveToQueue($_REQUEST['id'], $_REQUEST, 'webhook');
            }
            exit();
        }
        if (array_key_exists('m_pay', $_REQUEST)) {
            try {
                $raw = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId, cOrderId FROM `xplugin_ws5_mollie_orders` WHERE dReminder IS NOT NULL AND MD5(CONCAT(kId, "-", kBestellung)) = :md5', [
                    ':md5' => $_REQUEST['m_pay']
                ], 1);

                if (!$raw) {
                    throw new RuntimeException(self::Plugin('ws5_mollie')->getLocalization()->getTranslation('errOrderNotFound'));
                }

                if (strpos($raw->cOrderId, 'tr_') === 0) {
                    $checkout = PaymentCheckout::fromID($raw->cOrderId);
                } else {
                    $checkout = OrderCheckout::fromID($raw->cOrderId);
                }
                $checkout->getMollie(true);
                $checkout->updateModel()->saveModel();

                if ($checkout->getBestellung()->dBezahltDatum !== null || in_array($checkout->getModel()->cStatus, ['completed', 'paid', 'authorized', 'pending'])) {
                    throw new RuntimeException(self::Plugin('ws5_mollie')->getLocalization()->getTranslation('errAlreadyPaid'));
                }

                $options = [];
                if (self::Plugin('ws5_mollie')->getConfig()->getValue('resetMethod') !== 'on') {
                    $options['method'] = $checkout->getModel()->cMethod;
                }

                $mollie = $checkout->create($options); // Order::repayOrder($orderModel->getOrderId(), $options, $api);
                $url    = $mollie->getCheckoutUrl();

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
