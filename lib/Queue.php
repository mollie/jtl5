<?php


namespace Plugin\ws5_mollie\lib;


use Exception;
use Generator;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Shop;
use Mollie\Api\Types\OrderStatus;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\Traits\Plugin;
use RuntimeException;

class Queue
{

    use Plugin;


    /**
     * @param int $limit
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function run($limit = 10): void
    {

        /** @var QueueModel $todo */
        foreach (self::getOpen($limit) as $todo) {

            if ((list($type, $id) = explode(':', $todo->cType))) {
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
                    Shop::Container()->getLogService()->error($e->getMessage() . " ({$type}, {$id})");
                    $todo->done("{$e->getMessage()}\n{$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}");
                }
            }
        }
    }

    /**
     * @param $limit
     * @return Generator|null
     */
    private static function getOpen($limit): ?Generator
    {
        $open = Shop::Container()->getDB()->executeQueryPrepared("SELECT * FROM xplugin_ws5_mollie_queue WHERE dDone IS NULL AND `bLock` IS NULL AND (cType LIKE 'webhook:%%' OR (cType LIKE 'hook:%%') AND dCreated < DATE_SUB(NOW(), INTERVAL 3 MINUTE)) ORDER BY dCreated DESC LIMIT 0, :LIMIT;", [
            ':LIMIT' => $limit
        ], 2);

        foreach ($open as $_raw) {
            $queueModel = new QueueModel($_raw);
            yield $queueModel;
        }
    }

    /**
     * @param string $id
     * @param QueueModel $todo
     * @return bool
     * @throws Exception
     */
    protected static function handleWebhook(string $id, QueueModel $todo): bool
    {

        $checkout = AbstractCheckout::fromID($id);
        if ($checkout->getBestellung()->kBestellung && $checkout->getPaymentMethod()) {
            $checkout->handleNotification();
            return $todo->done('Status: ' . $checkout->getMollie()->status);
        }
        throw new RuntimeException("Bestellung oder Zahlungsart konnte nicht geladen werden: {$id}");
    }

    /**
     * @param int $hook
     * @param QueueModel $todo
     * @return bool
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @throws Exception
     */
    protected static function handleHook(int $hook, QueueModel $todo): bool
    {
        $data = unserialize($todo->getData()); //, [stdClass::class, Bestellung::class, \JTL\Customer\Customer::class]);
        if (array_key_exists('kBestellung', $data)) {
            switch ($hook) {
                case HOOK_BESTELLUNGEN_XML_BESTELLSTATUS:
                    if ((int)$data['kBestellung']) {
                        $checkout = AbstractCheckout::fromBestellung($data['kBestellung']);

                        $result = "";
                        if ((int)$checkout->getBestellung()->cStatus < BESTELLUNG_STATUS_VERSANDT) {
                            return $todo->done("Bestellung noch nicht versendet: {$checkout->getBestellung()->cStatus}");
                        }

                        /** @var $method PaymentMethod */
                        if ((int)$data['status']
                            && array_key_exists('status', $data)
                            && $checkout->getPaymentMethod()
                            && (strpos($checkout->getModel()->cOrderId, 'tr_') === false)
                            && $checkout->getMollie()) {
                            /** @var OrderCheckout $checkout */
                            $checkout->handleNotification();
                            if ($checkout->getMollie()->status === OrderStatus::STATUS_COMPLETED) {
                                $result = 'Mollie Status already ' . $checkout->getMollie()->status;
                            } else if ($checkout->getMollie()->isCreated() || $checkout->getMollie()->isPaid() || $checkout->getMollie()->isAuthorized() || $checkout->getMollie()->isShipping() || $checkout->getMollie()->isPending()) {
                                try {
                                    if ($shipments = Shipment::syncBestellung($checkout)) {
                                        foreach ($shipments as $shipment) {
                                            if (is_string($shipment)) {
                                                $checkout->getPaymentMethod()->doLog("Shipping-Error: {$shipment}");
                                                $result .= "Shipping-Error: {$shipment}\n";
                                            } else {
                                                $checkout->getPaymentMethod()->doLog("Order shipped: \n" . print_r($shipment, 1));
                                                $result .= "Order shipped: {$shipment->id}\n";
                                            }

                                        }
                                    } else {
                                        $result = 'No Shipments ready!';
                                    }
                                } catch (Exception $e) {
                                    $result = $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
                                }
                            } else {
                                $result = 'Unexpected Mollie Status: ' . $checkout->getMollie()->status;
                            }

                        } else {
                            $result = 'Nothing to do.';
                        }
                        return $todo->done($result);
                    }
                    return $todo->done("kBestellung missing");

                case HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO:
                    if (self::Plugin()->getConfig()->getValue('autoRefund') !== 'on') {
                        throw new RuntimeException('Auto-Refund disabled');
                    }

                    $checkout = AbstractCheckout::fromBestellung((int)$data['kBestellung']);
                    return $todo->done($checkout->cancelOrRefund());
            }
        }
        return false;
    }

    public static function storno($delay)
    {
        if (!$delay) {
            return true;
        }

        $open = Shop::Container()->getDB()->executeQueryPrepared("SELECT p.kId, b.cBestellNr, p.kBestellung, b.cStatus FROM xplugin_ws5_mollie_orders p JOIN tbestellung b ON b.kBestellung = p.kBestellung WHERE b.cStatus IN ('1', '2') AND p.dCreated < NOW() - INTERVAL :d HOUR",
            [':d' => $delay], 2);

        foreach ($open as $o) {
            try {

                $checkout = AbstractCheckout::fromBestellung($o->kBestellung);
                $pm = $checkout->getPaymentMethod();
                if ($pm::ALLOW_AUTO_STORNO && $pm::METHOD === $checkout->getMollie()->method) {
                    if ($checkout->getBestellung()->cAbgeholt === 'Y' && (bool)$checkout->getModel()->bSynced === false) {
                        if (!in_array($checkout->getMollie()->status, [OrderStatus::STATUS_PAID, OrderStatus::STATUS_COMPLETED, OrderStatus::STATUS_AUTHORIZED], true)) {
                            $checkout->storno();
                        } else {
                            $checkout->Log(sprintf('AutoStorno: Bestellung bezahlt? %s - Method: %s', $checkout->getMollie()->status, $checkout->getMollie()->method), LOGLEVEL_ERROR);
                        }
                    } else {
                        $checkout->Log('AutoStorno: bereits zur WAWI synchronisiert.', LOGLEVEL_ERROR);
                    }
                } else {
                    $checkout->Log(sprintf('AutoStorno aktiv: %d (%s) - Method: %s', (int)$pm::ALLOW_AUTO_STORNO, $pm::METHOD, $checkout->getMollie()->method), LOGLEVEL_ERROR);
                }

            } catch (Exception $e) {
                Shop::Container()->getLogService()->error(sprintf("Fehler beim stornieren der Order: %s / Bestellung: %s: %s", $o->cBestellNr, $o->kId, $e->getMessage()));
            }
        }
        return true;
    }

}