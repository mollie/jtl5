<?php


namespace Plugin\ws5_mollie\lib;


use JTL\Checkout\Lieferschein;
use JTL\Checkout\Lieferscheinpos;
use JTL\Checkout\Versand;
use JTL\Model\DataModel;
use Mollie\Api\Types\OrderStatus;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Traits\Jsonable;

class Shipment implements \JsonSerializable
{

    public $lines = [];
    public $tracking;
    public $testmode;

    use Jsonable;

    /**
     * @param int $kLieferschein
     * @param $orderId
     * @return Shipment[]
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public static function factory(int $kLieferschein, $orderId): Shipment
    {

        $orderModel = OrderModel::loadByAttributes(
            ['orderId' => $orderId],
            \Shop::Container()->getDB(),
            DataModel::ON_NOTEXISTS_FAIL);

        $mOrder = MollieAPI::API($orderModel->getTest())->orders->get($orderId);

        if ($mOrder->status === OrderStatus::STATUS_COMPLETED) {
            throw new \Exception('Bestellung bei Mollie bereits abgeschlossen!');
        }

        $oLieferschein = new Lieferschein($kLieferschein);


        if (!$oLieferschein->getLieferschein()) {
            throw new \Exception('Lieferschein konnte nicht geladen werden');
        }


        if (!count($oLieferschein->oVersand_arr)) {
            throw new \Exception('Kein Versand gefunden!');
        }


        $shipment = new self();
        $oVersand = new Versand($oLieferschein->oVersand_arr[0]->getVersand());
        if ($oVersand->getIdentCode() && $oVersand->getLogistik()) {
            $shipment->tracking = [
                'carrier' => $oVersand->getLogistik(),
                'code' => $oVersand->getIdentCode(),
            ];
            if ($oVersand->getLogistikVarUrl()) {
                $shipment->tracking['url'] = $oVersand->getLogistikURL();
            }
        }
        if ($orderModel->getTest()) {
            $shipment->testmode = true;
        }

        $shipment->lines = self::getOrderLines($oLieferschein, $mOrder);

        return $shipment;

    }

    protected static function getOrderLines(Lieferschein $oLieferschein, \Mollie\Api\Resources\Order $mOrder): array
    {
        $lines = [];

        if(!count($oLieferschein->oPosition_arr)){
            return $lines;
        }

        foreach ($mOrder->lines as $orderLine) {
            /** @var Lieferscheinpos $oLieferschienPos */
            foreach ($oLieferschein->oPosition_arr as $oPosition) {

                var_dump($oPosition);

            }

        }


        return $lines;
    }

}