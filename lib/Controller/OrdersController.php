<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Response;
use stdClass;

class OrdersController extends AbstractController
{

    public static function one(stdClass $data)
    {
        $order = Shop::Container()->getDB()->executeQueryPrepared("SELECT * FROM `xplugin_ws5_mollie_orders` WHERE cOrderId = :cOrderId;", [':cOrderId' => $data->id], 1);

        $result = (object)[
            'mollie' => MollieAPI::API((bool)$order->bTest)->orders->get($order->cOrderId, ['embed' => 'payments,shipments']),
            'order' => $order,
            'bestellung' => new Bestellung($order->kBestellung, true)
        ];
        return new Response($result);
    }


}