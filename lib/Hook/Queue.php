<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use Exception;
use JTL\Alert\Alert;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Helpers\Text;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\PluginHelper;
use RuntimeException;
use WS\JTL5\V1_0_16\Hook\AbstractHook;

class Queue extends AbstractHook
{
    /**
     * @param array $args_arr
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function bestellungInDB(array $args_arr): void
    {
        if (
            array_key_exists('oBestellung', $args_arr)
            && PluginHelper::getSetting('onlyPaid')
            && AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kZahlungsart, true)
        ) {
            $args_arr['oBestellung']->cAbgeholt = 'Y';
            Shop::Container()->getLogService()->info('Switch cAbgeholt for kBestellung: ' . print_r($args_arr['oBestellung']->kBestellung, 1));
        }
    }

    /**
     * @param array $args_arr
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function xmlBestellStatus(array $args_arr): void
    {
        if (AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            QueueModel::saveToQueue(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS . ':' . (int)$args_arr['oBestellung']->kBestellung, [
                'kBestellung' => $args_arr['oBestellung']->kBestellung,
                'status'      => (int)$args_arr['status']
            ]);
        }
    }

    /**
     * @param array $args_arr
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function xmlBearbeiteStorno(array $args_arr): void
    {
        if (AbstractCheckout::isMollie((int)$args_arr['oBestellung']->kBestellung)) {
            QueueModel::saveToQueue(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO . ':' . $args_arr['oBestellung']->kBestellung, ['kBestellung' => $args_arr['oBestellung']->kBestellung]);
        }
    }

    /**
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function headPostGet(): void
    {
        if (array_key_exists('mollie', $_REQUEST) && (int)$_REQUEST['mollie'] === 1 && array_key_exists('id', $_REQUEST)) {
            try {
                if (array_key_exists('hash', $_REQUEST) && $hash = trim(Text::htmlentities(Text::filterXSS($_REQUEST['hash'])), '_')) {
                    AbstractCheckout::finalizeOrder($hash, $_REQUEST['id'], array_key_exists('test', $_REQUEST));
                } else {
                    QueueModel::saveToQueue($_REQUEST['id'], $_REQUEST, 'webhook');
                }
            } catch (Exception $e) {
                Shop::Container()->getLogService()->error(__NAMESPACE__ . ' could not finalize order or add to queue: ' . $e->getMessage() . "\n" . json_encode($_REQUEST));
            }

            // TODO: DOKU
            ifndef('MOLLIE_STOP_EXEC_AFTER_WEBHOOK', true);
            if (MOLLIE_STOP_EXEC_AFTER_WEBHOOK) {
                exit();
            }
        }
        if (array_key_exists('m_pay', $_REQUEST)) {
            try {
                $raw = PluginHelper::getDB()->executeQueryPrepared('SELECT kId, cOrderId FROM `xplugin_ws5_mollie_orders` WHERE dReminder IS NOT NULL AND MD5(CONCAT(kId, "-", kBestellung)) = :md5', [
                    ':md5' => $_REQUEST['m_pay']
                ], 1);

                if (!$raw) {
                    throw new RuntimeException(PluginHelper::getPlugin()->getLocalization()->getTranslation('errOrderNotFound'));
                }

                if (strpos($raw->cOrderId, 'tr_') === 0) {
                    $checkout = PaymentCheckout::fromID($raw->cOrderId);
                } else {
                    $checkout = OrderCheckout::fromID($raw->cOrderId);
                }
                $checkout->getMollie(true);
                $checkout->updateModel()->saveModel();

                if ($checkout->getBestellung()->dBezahltDatum !== null || in_array($checkout->getModel()->cStatus, ['completed', 'paid', 'authorized', 'pending'])) {
                    throw new RuntimeException(PluginHelper::getPlugin()->getLocalization()->getTranslation('errAlreadyPaid'));
                }

                $options = [];
                if (!PluginHelper::getSetting('resetMethod')) {
                    $options['method'] = $checkout->getModel()->cMethod;
                }
                $mollie = $checkout->create($options);
                $url    = $mollie->getCheckoutUrl();

                header('Location: ' . $url);
                exit();
            } catch (RuntimeException $e) {
                $alertHelper = Shop::Container()->getAlertService();
                $alertHelper->addAlert(Alert::TYPE_ERROR, $e->getMessage(), 'mollie_repay', ['dismissable' => true]);
            } catch (Exception $e) {
                Shop::Container()->getLogService()->error('mollie:repay:error: ' . $e->getMessage() . "\n" . print_r($_REQUEST, 1));
            }
        }
    }
}
