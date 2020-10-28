<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Response;
use stdClass;

class OrdersController extends AbstractController
{
    public static function all(stdClass $data)
    {
        //$orders = OrderModel::loadAll(Shop::Container()->getDB(), [], []);
        $orders = Shop::Container()->getDB()->executeQueryPrepared("SELECT mo.*, b.cBestellNr, b.cStatus as cJTLStatus, b.kWaehrung, b.fGesamtsumme FROM `xplugin_ws5_mollie_orders` mo JOIN tbestellung b ON b.kBestellung = mo.kBestellung", [], 2);
        return new Response($orders);
    }

    public static function one(stdClass $data)
    {
        $order = Shop::Container()->getDB()->executeQueryPrepared("SELECT mo.*, b.cBestellNr, b.cStatus as cJTLStatus, b.kWaehrung, b.fGesamtsumme FROM `xplugin_ws5_mollie_orders` mo JOIN tbestellung b ON b.kBestellung = mo.kBestellung WHERE mo.kId = :kId;", [':kId' => (int)$data->id], 1);

        $result = (object)[
            'mollie' => MollieAPI::API((bool)$order->bTest)->orders->get($order->cOrderId),
            'order' => $order,
            'bestellung' => new Bestellung($order->kBestellung, true)
        ];
        return new Response($result);
    }
}