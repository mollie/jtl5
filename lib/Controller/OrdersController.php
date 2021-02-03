<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Checkout\Bestellung;
use JTL\Model\DataModel;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Order;
use Plugin\ws5_mollie\lib\PaymentMethod;
use Plugin\ws5_mollie\lib\Response;
use stdClass;

class OrdersController extends AbstractController
{

    public static function fetchable(stdClass $data): Response
    {

        $orderModel = OrderModel::loadByAttributes(
            ['orderId' => $data->id],
            Shop::Container()->getDB(),
            DataModel::ON_NOTEXISTS_FAIL);

        $oBestellung = new Bestellung($orderModel->bestellung);

        return new Response(PaymentMethod::makeFetchable($oBestellung, $orderModel));
    }

    public static function all(stdClass $data): Response
    {

        if (self::Plugin()->getConfig()->getValue('hideCompleted') === 'on') {

            $query = "SELECT o.*, b.cStatus as cJTLStatus, b.cAbgeholt, b.cVersandartName, b.cZahlungsartName, b.fGuthaben, b.fGesamtsumme "
                . "FROM xplugin_ws5_mollie_orders o "
                . "JOIN tbestellung b ON b.kbestellung = o.kBestellung "
                . "WHERE !(o.cStatus = 'completed' AND b.cSTatus = '4')"
                . "ORDER BY b.dErstellt DESC;";
            $data->query = $query;
        }
        return HelperController::selectAll($data);
    }

    public static function one(stdClass $data): Response
    {

        $order = Shop::Container()->getDB()
            ->executeQueryPrepared("SELECT * FROM `xplugin_ws5_mollie_orders` WHERE cOrderId = :cOrderId;",
                [':cOrderId' => $data->id],
                1);

        $mOrder = MollieAPI::API((bool)$order->bTest)->orders
            ->get($order->cOrderId, ['embed' => 'payments,shipments,refunds']);
        Order::update($mOrder);

        $result = (object)[
            'mollie' => $mOrder,
            'order' => $order,
            'bestellung' => new Bestellung($order->kBestellung, true),
            'logs' => Shop::Container()->getDB()
                ->executeQueryPrepared("SELECT * FROM `xplugin_ws5_mollie_queue` WHERE cType LIKE :cType",
                    [
                        ':cType' => "%{$order->cOrderId}%"
                    ], 2)
        ];
        return new Response($result);
    }

}