<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib;

use Exception;
use Generator;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Shop;
use JTL\Helpers\Request;
use Mollie\Api\Types\OrderStatus;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use RuntimeException;
use WS\JTL5\V1_0_16\Helper\AbstractPluginHelper;
use WS\JTL5\V1_0_16\Traits\Plugins;

class Queue
{
    use Plugins;


    /**
     * @param int $limit
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function runSynchronous(int $limit = 10): void
    {
        /** @var QueueModel $todo */
        foreach (self::getOpen($limit) as $todo) {
            if (!self::lock($todo)) {
                continue;
            }

            if (([$type, $id] = explode(':', $todo->cType))) {
                try {
                    switch ($type) {
                        case 'webhook':
                            self::handleWebhook($id, $todo);

                            break;
                        case 'hook':
                            self::handleHook((int)$id, $todo);

                            break;
                    }
                } catch (Exception $e) {
                    Shop::Container()->getLogService()->notice('Mollie Queue Fehler: ' . $e->getMessage() . " ($type, $id)");
                    $todo->cError = "{$e->getMessage()}\n{$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}";
                    $todo->done();
                }
            }

            self::unlock($todo);
        }

        ifndef('MOLLIE_REMINDER_PROP', 10);
        if (random_int(1, MOLLIE_REMINDER_PROP) % MOLLIE_REMINDER_PROP === 0) {
            /** @noinspection PhpUndefinedConstantInspection */
            $lock = new ExclusiveLock('mollie_reminder', PFAD_ROOT . PFAD_COMPILEDIR);
            if ($lock->lock()) {
                AbstractCheckout::sendReminders();
                Queue::storno(PluginHelper::getSetting('autoStorno'));
            }
        }
    }


    /**
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function runAsynchronous(): void
    {
        ifndef('MOLLIE_QUEUE_MAX', 3);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && Request::isAjaxRequest()) {
            $limit = MOLLIE_QUEUE_MAX;
        } else {
            $response = [
                "status" => "error",
                "message" => "Invalid request"
            ];

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        /** @var QueueModel $todo */
        foreach (self::getOpen($limit) as $todo) {
            if (!self::lock($todo)) {
                continue;
            }

            if (([$type, $id] = explode(':', $todo->cType))) {
                try {
                    switch ($type) {
                        case 'webhook':
                            self::handleWebhook($id, $todo);

                            break;
                        case 'hook':
                            self::handleHook((int)$id, $todo);

                            break;
                    }
                } catch (Exception $e) {
                    Shop::Container()->getLogService()->notice('Mollie Queue Fehler: ' . $e->getMessage() . " ($type, $id)");
                    $todo->cError = "{$e->getMessage()}\n{$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}";
                    $todo->done();
                }
            }

            self::unlock($todo);
        }

        ifndef('MOLLIE_REMINDER_PROP', 10);
        if (random_int(1, MOLLIE_REMINDER_PROP) % MOLLIE_REMINDER_PROP === 0) {
            /** @noinspection PhpUndefinedConstantInspection */
            $lock = new ExclusiveLock('mollie_reminder', PFAD_ROOT . PFAD_COMPILEDIR);
            if ($lock->lock()) {
                AbstractCheckout::sendReminders();
                Queue::storno(PluginHelper::getSetting('autoStorno'));
            }
        }

        $response = [
            "status" => "success",
            "data" => [
                "message" => "Request was successful"
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }


    /**
     * @param int $limit
     * @return Generator<QueueModel>
     */
    private static function getOpen(int $limit): Generator
    {
        if (!defined('MOLLIE_HOOK_DELAY')) {
            define('MOLLIE_HOOK_DELAY', 3);
        }
        $open = PluginHelper::getDB()->executeQueryPrepared("SELECT * FROM xplugin_ws5_mollie_queue WHERE (dDone IS NULL OR dDone = '0000-00-00 00:00:00') AND `bLock` IS NULL AND (cType LIKE 'webhook:%%' OR (cType LIKE 'hook:%%' AND dCreated < DATE_SUB(NOW(), INTERVAL :hd MINUTE))) ORDER BY dCreated DESC LIMIT 0, :LIMIT;", [
            ':LIMIT' => $limit,
            ':hd' => MOLLIE_HOOK_DELAY
        ], 2);

        foreach ($open as $_raw) {
            yield new QueueModel($_raw);
        }
    }


    /**
     * @param QueueModel $todo
     * @return bool
     */
    protected static function lock(QueueModel $todo): bool
    {
        // Validation should not be necessary here since QueueModel::TABLE is a constant, but we do it anyway to lead by example
        if (!AbstractPluginHelper::isAlphaNumericPlus(QueueModel::TABLE)) {
            return false;
        }

        return $todo->kId && PluginHelper::getDB()->executeQueryPrepared(sprintf('UPDATE %s SET `bLock` = NOW() WHERE `bLock` IS NULL AND kId = :kId', QueueModel::TABLE), [
                'kId' => $todo->kId
            ], 3) >= 1;
    }

    /**
     * @param string     $id
     * @param QueueModel $todo
     * @throws Exception
     * @return bool
     */
    protected static function handleWebhook(string $id, QueueModel $todo): bool
    {
        $checkout = AbstractCheckout::fromID($id);
        if ($checkout->getBestellung()->kBestellung && $checkout->getPaymentMethod()) {
            $checkout->handleNotification();
            
            return $todo->done('Status: ' . $checkout->getMollie()->status);
        }

        throw new RuntimeException("Bestellung oder Zahlungsart konnte nicht geladen werden: $id");
    }

    /**
     * @param int        $hook
     * @param QueueModel $queueModel
     * @return bool
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @throws Exception
     */
    protected static function handleHook(int $hook, QueueModel $queueModel): bool
    {
        $data = unserialize($queueModel->cData); //, [stdClass::class, Bestellung::class, \JTL\Customer\Customer::class]);
        if (array_key_exists('kBestellung', $data)) {
            switch ($hook) {
                case HOOK_BESTELLUNGEN_XML_BESTELLSTATUS:
                    if ((int)$data['kBestellung']) {
                        $checkout = AbstractCheckout::fromBestellung($data['kBestellung']);

                        $result = '';
                        if ((int)$checkout->getBestellung()->cStatus < BESTELLUNG_STATUS_VERSANDT) {
                            return $queueModel->done("Bestellung noch nicht versendet: {$checkout->getBestellung()->cStatus}");
                        }

                        if (!count($checkout->getBestellung()->oLieferschein_arr)) {
                            if (!defined('MOLLIE_HOOK_DELAY')) {
                                define('MOLLIE_HOOK_DELAY', 3);
                            }
                            $queueModel->dCreated = date('Y-m-d H:i:s', strtotime(sprintf('+%d MINUTES', MOLLIE_HOOK_DELAY)));
                            $queueModel->cResult  = 'Noch keine Lieferscheine, delay...';

                            return $queueModel->save();
                        }

                        if (
                            (int)$data['status']
                            && array_key_exists('status', $data)
                            && $checkout->getPaymentMethod()
                            && (strpos($checkout->getModel()->cOrderId, 'tr_') === false)
                            && $checkout->getMollie()
                        ) {
                            /** @var OrderCheckout $checkout */
                            $checkout->handleNotification();
                            if ($checkout->getMollie()->status === OrderStatus::STATUS_COMPLETED) {
                                $result = 'Mollie Status already ' . $checkout->getMollie()->status;
                            } elseif (
                                $checkout->getMollie()->isCreated()
                                || $checkout->getMollie()->isPaid()
                                || $checkout->getMollie()->isAuthorized()
                                || $checkout->getMollie()->isShipping()
                                || $checkout->getMollie()->isPending()
                            ) {
                                try {
                                    if ($shipments = Shipment::syncBestellung($checkout)) {
                                        foreach ($shipments as $shipment) {
                                            if (is_string($shipment)) {
                                                $checkout->Log("Shipping-Error: $shipment");
                                                $result .= "Shipping-Error: $shipment\n";
                                            } else {
                                                $checkout->Log("Order shipped: $shipment->id");
                                                $result .= "Order shipped: $shipment->id\n";
                                            }
                                        }
                                    } else {
                                        $result = 'No Shipments ready!';
                                    }
                                } catch (Exception $e) {
                                    $result = $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
                                }
                            } else {
                                $result = 'Unexpected Mollie Status: ' . $checkout->getMollie()->status;
                            }
                        } else {
                            $result = 'Nothing to do.';
                        }

                        return $queueModel->done($result);
                    }

                    return $queueModel->done('kBestellung missing');
                case HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO:
                    if (!PluginHelper::getSetting('autoRefund')) {
                        throw new RuntimeException('Auto-Refund disabled');
                    }
                    $checkout = AbstractCheckout::fromBestellung((int)$data['kBestellung']);

                    if (!isset($checkout)){
                        return $queueModel->done("No Checkout found for kBestellung:" . (int)$data['kBestellung']);
                    }
                    return $queueModel->done($checkout->cancelOrRefund());
            }
        }

        return false;
    }

    /**
     * @param QueueModel $qm
     * @return bool
     */
    protected static function unlock(QueueModel $qm): bool
    {
        // Validation should not be necessary here since QueueModel::TABLE is a constant, but we do it anyway to lead by example
        if (!AbstractPluginHelper::isAlphaNumericPlus(QueueModel::TABLE)) {
            return false;
        }

        return $qm->kId && PluginHelper::getDB()->executeQueryPrepared(sprintf('UPDATE %s SET `bLock` = NULL WHERE kId = :kId OR bLock < DATE_SUB(NOW(), INTERVAL 15 MINUTE)', QueueModel::TABLE), [
                'kId' => $qm->kId
            ], 3) >= 1;
    }

    /**
     * @param mixed $delay
     * @throws ServiceNotFoundException
     * @throws CircularReferenceException
     * @return true
     */
    public static function storno($delay): bool
    {
        if (!$delay) {
            return true;
        }

        $open = PluginHelper::getDB()->executeQueryPrepared(
            "SELECT p.kId, b.cBestellNr, p.kBestellung, b.cStatus FROM xplugin_ws5_mollie_orders p JOIN tbestellung b ON b.kBestellung = p.kBestellung WHERE b.cStatus IN ('1', '2') AND p.dCreated < NOW() - INTERVAL :d HOUR",
            [':d' => $delay],
            2
        );

        foreach ($open as $o) {
            try {
                $checkout = AbstractCheckout::fromBestellung($o->kBestellung);
                $pm       = $checkout->getPaymentMethod();
                if ($checkout->getBestellung()->cAbgeholt === 'Y' && (bool)$checkout->getModel()->bSynced === false) {
                    if ($pm::ALLOW_AUTO_STORNO && $pm::METHOD === $checkout->getMollie()->method) {
                        if (!in_array($checkout->getMollie()->status, [OrderStatus::STATUS_PAID, OrderStatus::STATUS_COMPLETED, OrderStatus::STATUS_AUTHORIZED], true)) {
                            $checkout->storno();
                        } else {
                            $checkout->Log(sprintf('AutoStorno: Bestellung bezahlt? %s - Method: %s', $checkout->getMollie()->status, $checkout->getMollie()->method), LOGLEVEL_ERROR);
                        }
                    }
                     else {
                         $checkout->Log(sprintf('AutoStorno aktiv: %d (%s) - Method: %s', (int)$pm::ALLOW_AUTO_STORNO, $pm::METHOD, $checkout->getMollie()->method), LOGLEVEL_ERROR);
                     }
                } else {
                    //$checkout->Log('AutoStorno: bereits zur WAWI synchronisiert.', LOGLEVEL_ERROR);
                }
            } catch (Exception $e) {
                Shop::Container()->getLogService()->error(sprintf('Fehler beim stornieren der Order: %s / Bestellung: %s: %s', $o->cBestellNr, $o->kId, $e->getMessage()));
            }
        }

        return true;
    }
}
