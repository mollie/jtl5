<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use Exception;
use JTL\Alert\Alert;
use JTL\Checkout\Bestellung;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Helpers\Text;
use JTL\Shop;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\PluginHelper;
use RuntimeException;
use WS\JTL5\V2_0_7\Hook\AbstractHook;

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
                'status' => (int)$args_arr['status']
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
                $url = $mollie->getCheckoutUrl();

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


    /** Check if order was already finalized or cancelled
     * This is called via AJAX from processPaymentPage to redirect customer when webhook call from mollie is completed
     *
     * @return void
     */
    public static function checkPaymentStatus(): void
    {
        try {
            header('Content-Type: application/json');

            if (isset($_GET['hash'])) {
                $sessionHash = $_GET['hash'];
                $paymentSession = PluginHelper::getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash);
                if ($paymentSession && $paymentSession->kBestellung) {
                    // Order was finalized (return Status 200 Success): customer will be redirected to success url
                    http_response_code(200);
                    $response = [
                        "status" => 200,
                        "data" => [
                            "message" => "Order finalized successfully.",
                            "success" => true,
                        ]
                    ];
                    echo json_encode($response);
                    exit;
                } else {
                    $order = PluginHelper::getDB()->select('xplugin_ws5_mollie_orders', 'cHash', '_' . $sessionHash);

                    if (str_starts_with($order->cOrderId, 'ord_')) {
                        // Order was cancelled or failed, but webhook was not called because it was an order via OrderAPI (return Status 422 Unprocessable Content): customer will be redirected to error url
                        $api     = new MollieAPI(true);
                        $mollie  = $api->getClient()->orders->get($order->cOrderId, ['embed' => 'payments']);
                        foreach ($mollie->payments() as $payment) {
                            if (in_array($payment->status, [PaymentStatus::STATUS_CANCELED, PaymentStatus::STATUS_EXPIRED, PaymentStatus::STATUS_FAILED])) {
                                // Order was cancelled or failed (return Status 422 Unprocessable Content): customer will be redirected to error url
                                http_response_code(422);
                                $response = [
                                    "status" => 422,
                                    "data" => [
                                        "message" => "Order cancelled.",
                                        "success" => false,
                                    ]
                                ];
                                echo json_encode($response);
                                exit;
                            }
                        }
                    }

                    if (in_array($order->cStatus, [OrderStatus::STATUS_CANCELED, OrderStatus::STATUS_EXPIRED, 'failed'], true)) {
                        // Order was cancelled or failed (return Status 422 Unprocessable Content): customer will be redirected to error url
                        http_response_code(422);
                        $response = [
                            "status" => 422,
                            "data" => [
                                "message" => "Order cancelled.",
                                "success" => false,
                            ]
                        ];
                        echo json_encode($response);
                        exit;
                    }
                }
            }

            // Still waiting on webhook call from mollie (return Status 202 Accepted): customer will not be redirected while order is pending
            http_response_code(202);
            $response = [
                "status" => 202,
                "data" => [
                    "message" => "Order not finalized yet.",
                    "success" => false,
                ]
            ];

            echo json_encode($response);
            exit;

        } catch (\Exception $e) {
            // Unknown error occures (return Status 500 Internal Server Error): customer will be redirected to error url
            http_response_code(500);
            $response = [
                "status" => 500,
                "data" => [
                    "message" => "Error: " . $e->getMessage(),
                    "success" => false,
                ]
            ];
            echo json_encode($response);
            exit;
        }
    }
}
