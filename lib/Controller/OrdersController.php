<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Model\ShipmentsModel;
use Plugin\ws5_mollie\lib\Response;
use stdClass;
use WS\JTL5\Backend\Controller\AbstractController;

class OrdersController extends AbstractController
{
    public static function fetchable(stdClass $data): Response
    {
        $orderModel = OrderModel::fromID($data->id, 'cOrderId', true);

        $oBestellung = new Bestellung($orderModel->kBestellung);

        return new Response(AbstractCheckout::makeFetchable($oBestellung, $orderModel));
    }

    public static function shipments(stdClass $data): Response
    {
        $response = [];
        if ($data->kBestellung) {
            $lieferschien_arr = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM tlieferschein WHERE kInetBestellung = :kBestellung', [
                ':kBestellung' => (int)$data->kBestellung
            ], 2);

            foreach ($lieferschien_arr as $lieferschein) {
                $shipmentsModel = ShipmentsModel::fromID((int)$lieferschein->kLieferschein, 'kLieferschein', false);

                $response[] = (object)[
                    'kLieferschein'   => $lieferschein->kLieferschein,
                    'cLieferscheinNr' => $lieferschein->cLieferscheinNr,
                    'cHinweis'        => $lieferschein->cHinweis,
                    'dErstellt'       => date('Y-m-d H:i:s', $lieferschein->dErstellt),
                    'shipment'        => $shipmentsModel->kBestellung ? $shipmentsModel : null,
                ];
            }
        }

        return new Response($response);
    }

    public static function all(stdClass $data): Response
    {
        if (self::Plugin()->getConfig()->getValue('hideCompleted') === 'on') {
            $query = 'SELECT o.*, b.cStatus as cJTLStatus, b.cAbgeholt, b.cVersandartName, b.cZahlungsartName, b.fGuthaben, b.fGesamtsumme '
                . 'FROM xplugin_ws5_mollie_orders o '
                . 'JOIN tbestellung b ON b.kbestellung = o.kBestellung '
                . "WHERE !(o.cStatus = 'completed' AND b.cStatus = '4')"
                . 'ORDER BY b.dErstellt DESC;';
            $data->query = $query;
        }

        return HelperController::selectAll($data);
    }

    public static function one(stdClass $data): Response
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
        $result['logs']       = Shop::Container()->getDB()
            ->executeQueryPrepared(
                'SELECT * FROM `xplugin_ws5_mollie_queue` WHERE cType LIKE :cTypeWebhook OR cType LIKE :cTypeHook',
                [
                    ':cTypeWebhook' => "%{$checkout->getModel()->cOrderId}%",
                    ':cTypeHook'    => "%:{$checkout->getModel()->kBestellung}%"
                ],
                2
            );

        return new Response($result);
    }

    public static function reminder(stdClass $data): Response
    {
        return new Response(AbstractCheckout::sendReminder($data->id));
    }

    public static function zalog(stdClass $data)
    {
        if ($data->id && $data->kBestellung) {
            $logs = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM tzahlungslog WHERE cLogData LIKE :cLogData1 OR cLogData LIKE :cLogData2 ORDER BY dDatum DESC', [
                ':cLogData1' => sprintf('%%#%d%%', (int)$data->kBestellung),
                ':cLogData2' => sprintf('%%$%s%%', trim($data->id))
            ], 2);

            return new Response($logs);
        }

        return new Response([]);
    }
}
