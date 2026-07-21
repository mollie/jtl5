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
use WS\JTL5\V2_0_7\Backend\AbstractResult;
use WS\JTL5\V2_0_7\Backend\Controller\AbstractController;

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

    public static function fetchMollieOrders(?stdClass $data = null): AbstractResult
    {
        $page = isset($data->page) ? max(1, (int)$data->page) : 1;
        $pageSize = isset($data->pageSize) ? (int)$data->pageSize : 500;
        $pageSize = max(1, min(1000, $pageSize));
        $offset = ($page - 1) * $pageSize;

        if (PluginHelper::getSetting('hideCompleted')) {
            $whereClause = "WHERE !(o.cStatus IN ('paid', 'completed') AND b.cStatus = '4')";
        } else {
            $whereClause = '';
        }
        $baseFrom = " FROM xplugin_ws5_mollie_orders o JOIN tbestellung b ON b.kbestellung = o.kBestellung {$whereClause}";

        $countQuery = "SELECT COUNT(*) AS total{$baseFrom}";
        $totalResult = PluginHelper::getDB()->executeQuery($countQuery, 1);
        $total = isset($totalResult->total) ? (int)$totalResult->total : 0;

        $sqlQuery = "SELECT o.*, b.cStatus AS cJTLStatus, b.cAbgeholt, b.cVersandartName, b.cZahlungsartName, b.fGuthaben, b.fGesamtsumme{$baseFrom} ORDER BY b.dErstellt DESC LIMIT :limit OFFSET :offset";
        $results = PluginHelper::getDB()->executeQueryPrepared($sqlQuery, [
            ':limit' => $pageSize,
            ':offset' => $offset
        ], 2);

        return new AbstractResult((object)[
            'items' => $results,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize
        ]);
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
     * @throws Exception
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
     * @return AbstractResult
     * @throws Exception
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
