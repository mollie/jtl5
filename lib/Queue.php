<?php


namespace Plugin\ws5_mollie\lib;


use Exception;
use Generator;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Shop;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
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

            if ((list($type, $id) = explode(':', $todo->getType()))) {
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
                    $todo->setResult($e->getMessage());
                    $todo->setDone(date('Y-m-d H:i:s'));
                    $todo->save();
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
        $open = Shop::Container()->getDB()->executeQueryPrepared("SELECT * FROM xplugin_ws5_mollie_queue WHERE cResult IS NULL AND dDone IS NULL ORDER BY dCreated DESC LIMIT 0, :LIMIT;", [
            ':LIMIT' => $limit
        ], 2);

        foreach ($open as $_raw) {
            $queueModel = QueueModel::newInstance(Shop::Container()->getDB());
            $queueModel->fill($_raw);
            $queueModel->setWasLoaded(true);
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
            $todo->setResult('Status: ' . $checkout->getMollie()->status);
            $todo->setDone(date('Y-m-d H:i:s'));
            return $todo->save();
        }
        throw new RuntimeException(`Bestellung oder Zahlungsart konnte nicht geladen werden: ${id}`);
    }

    /**
     * @param int $hook
     * @param QueueModel $todo
     * @return bool
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @throws ApiException
     * @throws IncompatiblePlatform
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

                        /** @var $method PaymentMethod */
                        if ((int)$data['status']
                            && array_key_exists('status', $data)
                            && $checkout->getBestellung()->kBestellung
                            && $checkout->getPaymentMethod()
                            && (strpos($checkout->getModel()->getOrderId(), 'tr_') === false)
                            && $checkout->getMollie()) {
                            /** @var OrderCheckout $checkout */
                            $checkout->handleNotification();
                            if ($checkout->getMollie()->isCreated() || $checkout->getMollie()->isPaid() || $checkout->getMollie()->isAuthorized() || $checkout->getMollie()->isShipping() || $checkout->getMollie()->isPending()) {
                                try {
                                    if ($shipments = Shipment::syncBestellung($checkout)) {
                                        foreach ($shipments as $shipment) {
                                            $checkout->getPaymentMethod()->doLog("Order shipped: \n" . print_r($shipment, 1));
                                        }
                                        $todo->setResult("Shipped " . count($shipments) . " shipments.");
                                    }
                                } catch (Exception $e) {
                                    $todo->setResult($e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
                                }
                            } else {
                                $todo->setResult('Unexpected Mollie Status: ' . $checkout->getMollie()->status);
                            }

                        } else {
                            $todo->setResult('Nothing to do.');
                        }

                        $todo->setDone(date('Y-m-d H:i:s'));
                        return $todo->save();

                    }

                    $todo->setResult("kBestellung missing");
                    $todo->setDone(date('Y-m-d H:i:s'));
                    return $todo->save();

                case HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO:
                    if (self::Plugin()->getConfig()->getValue('autoRefund') !== 'on') {
                        throw new RuntimeException('Auto-Refund disabled');
                    }

                    $checkout = AbstractCheckout::fromBestellung((int)$data['kBestellung']);
                    $todo->setResult($checkout->cancelOrRefund());
                    $todo->setDone(date('Y-m-d H:i:s'));
                    return $todo->save();

            }
        }
        return false;
    }

}