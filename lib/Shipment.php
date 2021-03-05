<?php


namespace Plugin\ws5_mollie\lib;


use Exception;
use JsonSerializable;
use JTL\Checkout\Bestellung;
use JTL\Checkout\Lieferschein;
use JTL\Checkout\Lieferscheinpos;
use JTL\Checkout\Versand;
use JTL\Model\DataModel;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Types\OrderStatus;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Model\ShipmentsModel;
use Plugin\ws5_mollie\lib\Traits\Jsonable;
use Shop;

class Shipment implements JsonSerializable
{

    /**
     * @var array
     */
    public array $lines = [];

    /**
     * @var array
     */
    public array $tracking;

    /**
     * @var bool
     */
    public $bTestmode;

    /**
     * @var int
     */
    protected $kBestellung;

    /**
     * @var int
     */
    protected $kLieferschein;

    /**
     * @var string
     */
    protected $cOrderId;

    use Jsonable;

    /**
     * @param int $kLieferschein
     * @param $orderId
     * @return Shipment
     * @throws ApiException
     * @throws IncompatiblePlatform
     * @throws Exception
     */
    public static function factory(int $kLieferschein, $orderId): Shipment
    {


        $orderModel = OrderModel::loadByAttributes(
            ['orderId' => $orderId],
            Shop::Container()->getDB(),
            DataModel::ON_NOTEXISTS_FAIL);

        $shipmentsModel = ShipmentsModel::loadByAttributes(
            ['lieferschein' => (int)$kLieferschein],
            \JTL\Shop::Container()->getDB(),
            DataModel::ON_NOTEXISTS_NEW);
        if ($shipmentsModel->getOrderId()) {
            throw new \Plugin\ws5_mollie\lib\Exception\APIException('Lieferschien bereits an Mollie übertragen!');
        }

        $mOrder = MollieAPI::API($orderModel->getTest())->orders->get($orderId);

        if ($mOrder->status === OrderStatus::STATUS_COMPLETED) {
            throw new \Plugin\ws5_mollie\lib\Exception\APIException('Bestellung bei Mollie bereits abgeschlossen!');
        }

        $oLieferschein = new Lieferschein($kLieferschein);


        if (!$oLieferschein->getLieferschein()) {
            throw new \Plugin\ws5_mollie\lib\Exception\APIException('Lieferschein konnte nicht geladen werden');
        }


        if (!count($oLieferschein->oVersand_arr)) {
            throw new \Plugin\ws5_mollie\lib\Exception\APIException('Kein Versand gefunden!');
        }


        $shipment = new self();

        $shipment->cOrderId = $orderId;
        $shipment->kBestellung = $orderModel->getBestellung();
        $shipment->kLieferschein = $kLieferschein;

        /** @var Versand $oVersand */
        $oVersand = $oLieferschein->oVersand_arr[0];
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
            $shipment->bTestmode = true;
        }

        $oBestellung = new Bestellung($orderModel->getBestellung());
        if ((int)$oBestellung->cStatus === BESTELLUNG_STATUS_VERSANDT) {
            $shipment->lines = [];
        } else {
            $shipment->lines = self::getOrderLines($oLieferschein, $mOrder);
        }

        return $shipment;

    }

    protected static function getOrderLines(Lieferschein $oLieferschein, \Mollie\Api\Resources\Order $mOrder): array
    {
        $lines = [];

        if (!count($oLieferschein->oLieferscheinPos_arr)) {
            return $lines;
        }

        // Bei Stücklisten, sonst gibt es mehrere OrderLines für die selbe ID
        $shippedOrderLines = [];

        /** @var Lieferscheinpos $oLieferschienPos */
        foreach ($oLieferschein->oLieferscheinPos_arr as $oLieferschienPos) {

            $wkpos = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM twarenkorbpos WHERE kBestellpos = :kBestellpos', [
                ':kBestellpos' => $oLieferschienPos->getBestellPos()
            ], 1);

            /** @var OrderLine $orderLine */
            foreach ($mOrder->lines as $orderLine) {

                if (!in_array($orderLine->id, $shippedOrderLines, true) && $wkpos->cArtNr === $orderLine->sku) {

                    $quantity = min($oLieferschienPos->getAnzahl(), $orderLine->shippableQuantity);
                    if ($quantity) {
                        $lines[] = [
                            'id' => $orderLine->id,
                            'quantity' => min($oLieferschienPos->getAnzahl(), $orderLine->shippableQuantity)
                        ];
                    }
                    $shippedOrderLines[] = $orderLine->id;
                    break;
                }

            }
        }


        return $lines;
    }

    /**
     * @return bool
     * @throws ApiException
     * @throws IncompatiblePlatform
     * @throws Exception
     */
    public function send(): bool
    {

        $oShipmentModel = new ShipmentsModel(Shop::Container()->getDB());
        $oShipmentModel->setBestellung($this->kBestellung);
        $oShipmentModel->setLieferschein($this->kLieferschein);
        $oShipmentModel->setOrderId($this->cOrderId);
        $oShipmentModel->setCarrier($this->tracking['carrier'] ?? null);
        $oShipmentModel->setCode($this->tracking['code'] ?? null);
        $oShipmentModel->setUrl($this->tracking['url'] ?? null);

        $api = MollieAPI::API($this->bTestmode);
        $this->bTestmode = null;
        $this->cOrderId = null;
        $this->kLieferschein = null;
        $this->kBestellung = null;

        //return true;

        $mShipping = $api->shipments->createForId($oShipmentModel->orderId, $this->toArray());

        $oShipmentModel->setShipmentId($mShipping->id);

        return $oShipmentModel->save();

    }

}