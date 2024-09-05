<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use Exception;
use JTL\Checkout\Bestellung;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Model\ShipmentsModel;
use Plugin\ws5_mollie\lib\PluginHelper;
use stdClass;
use WS\JTL5\V1_0_16\Backend\AbstractResult;
use WS\JTL5\V1_0_16\Backend\Controller\AbstractController;

/**
 * Class OrdersController
 * @package Plugin\ws5_mollie\lib\Controller
 */
class OrdersController extends AbstractController
{
    /**
     * @throws ServiceNotFoundException
     * @throws CircularReferenceException
     */
    public static function fetchable(stdClass $data): AbstractResult
    {
        $orderModel = OrderModel::fromID($data->id, 'cOrderId', true);

        $oBestellung = new Bestellung($orderModel->kBestellung);

        return new AbstractResult(AbstractCheckout::makeFetchable($oBestellung, $orderModel));
    }

    /**
     * @param stdClass $data
     * @return AbstractResult
     */
    public static function shipments(stdClass $data): AbstractResult
    {
        $response = [];
        if ($data->kBestellung) {
            $lieferschein_arr = PluginHelper::getDB()->executeQueryPrepared('SELECT * FROM tlieferschein WHERE kInetBestellung = :kBestellung', [
                ':kBestellung' => (int)$data->kBestellung
            ], 2);

            foreach ($lieferschein_arr as $lieferschein) {
                $shipmentsModel = ShipmentsModel::fromID((int)$lieferschein->kLieferschein, 'kLieferschein', false);

                $response[] = (object)[
                    'kLieferschein' => $lieferschein->kLieferschein,
                    'cLieferscheinNr' => $lieferschein->cLieferscheinNr,
                    'cHinweis' => $lieferschein->cHinweis,
                    'dErstellt' => date('Y-m-d H:i:s', $lieferschein->dErstellt),
                    'shipment' => $shipmentsModel->kBestellung ? $shipmentsModel : null,
                ];
            }
        }

        return new AbstractResult($response);
    }

    /**
     * @param stdClass $data
     * @return AbstractResult
     */
    public static function all(stdClass $data): AbstractResult
    {
        if (PluginHelper::getSetting('hideCompleted')) {
            $query = 'SELECT o.*, b.cStatus as cJTLStatus, b.cAbgeholt, b.cVersandartName, b.cZahlungsartName, b.fGuthaben, b.fGesamtsumme '
                . 'FROM xplugin_ws5_mollie_orders o '
                . 'JOIN tbestellung b ON b.kbestellung = o.kBestellung '
                . "WHERE !(o.cStatus = 'completed' AND b.cStatus = '4')"
                . 'ORDER BY b.dErstellt DESC;';
            $data->query = $query;
        }

        return HelperController::selectAll($data);
    }

    /**
     * @param stdClass $data
     * @throws Exception
     * @return AbstractResult
     */
    public static function one(stdClass $data): AbstractResult
    {
        $result = [];
        if (strpos($data->id, 'tr_') !== false) {
            $checkout = PaymentCheckout::fromID($data->id);
        } else {
            $checkout = OrderCheckout::fromID($data->id);
        }

        $checkout->updateModel()->saveModel();

        $result['mollie']     = $checkout->getMollie();
        $result['order']      = $checkout->getModel()->jsonSerialize();
        $result['bestellung'] = $checkout->getBestellung();
        $result['logs'] = PluginHelper::getDB()
            ->executeQueryPrepared(
                'SELECT * FROM `xplugin_ws5_mollie_queue` WHERE cType LIKE :cTypeWebhook OR cType LIKE :cTypeHook',
                [
                    ':cTypeWebhook' => "%{$checkout->getModel()->cOrderId}%",
                    ':cTypeHook' => "%:{$checkout->getModel()->kBestellung}%"
                ],
                2
            );

        return new AbstractResult($result);
    }

    /**
     * @param stdClass $data
     * @throws Exception
     * @return AbstractResult
     */
    public static function get(stdClass $data): AbstractResult
    {
        if (strpos($data->id, 'tr_') !== false) {
            $checkout = PaymentCheckout::fromID($data->id);
        } else {
            $checkout = OrderCheckout::fromID($data->id);
        }
        $checkout->updateModel()->saveModel();

        return new AbstractResult($checkout->getBestellung());
    }

    public static function getQueue(stdClass $data): AbstractResult
    {
        if (strpos($data->id, 'tr_') !== false) {
            $checkout = PaymentCheckout::fromID($data->id);
        } else {
            $checkout = OrderCheckout::fromID($data->id);
        }

        $checkout->updateModel()->saveModel();

        return new AbstractResult(PluginHelper::getDB()
            ->executeQueryPrepared(
                'SELECT * FROM `xplugin_ws5_mollie_queue` WHERE cType LIKE :cTypeWebhook OR cType LIKE :cTypeHook',
                [
                    ':cTypeWebhook' => "%{$checkout->getModel()->cOrderId}%",
                    ':cTypeHook' => "%:{$checkout->getModel()->kBestellung}%"
                ],
                2
            ));
    }


    /**
     * @param stdClass $data
     * @throws Exception
     * @return AbstractResult
     */
    public static function reminder(stdClass $data): AbstractResult
    {
        return new AbstractResult(AbstractCheckout::sendReminder($data->id));
    }

    /**
     * @param stdClass $data
     * @return AbstractResult
     */
    public static function zalog(stdClass $data)
    {
        if ($data->id && $data->kBestellung) {
            $logs = PluginHelper::getDB()->executeQueryPrepared('SELECT * FROM tzahlungslog WHERE cLogData LIKE :cLogData1 OR cLogData LIKE :cLogData2 ORDER BY dDatum DESC', [
                ':cLogData1' => sprintf('%%#%d%%', (int)$data->kBestellung),
                ':cLogData2' => sprintf('%%$%s%%', trim($data->id))
            ], 2);

            return new AbstractResult($logs);
        }

        return new AbstractResult();
    }
}
