<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Checkout\Bestellung;
use Kunde;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Response;
use Plugin\ws5_mollie\lib\Shipment;
use stdClass;

class ShipmentsController extends AbstractController
{


    /**
     * @param stdClass $data
     * @return Response
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public static function sync(stdClass $data): Response
    {

        if (!$data->kBestellung || !$data->kLieferschein || !$data->orderId) {
            throw new \Plugin\ws5_mollie\lib\Exception\APIException('Bestellung, Liefererschien oder Mollie OrderId fehlen.');
        }

        $checkout = OrderCheckout::fromID($data->orderId);

        if ($checkout->getModel()->getBestellung()) {
            $shipment = Shipment::factory((int)$data->kLieferschein, $checkout);

            $oKunde = new Kunde($checkout->getBestellung()->kKunde);

            $mode = self::Plugin()->getConfig()->getValue('shippingMode');
            switch ($mode) {
                case 'A':
                    // ship directly
                    if (!$shipment->send() && !$shipment->result) {
                        throw new \Plugin\ws5_mollie\lib\Exception\APIException('Shipment konnte nicht gespeichert werden.');
                    }
                    return new Response(true);

                case 'B':
                    // only ship if complete shipping
                    if ($oKunde->nRegistriert || (int)$checkout->getBestellung()->cStatus === BESTELLUNG_STATUS_VERSANDT) {
                        if (!$shipment->send() && !$shipment->result) {
                            throw new \Plugin\ws5_mollie\lib\Exception\APIException('Shipment konnte nicht gespeichert werden.');
                        }
                        return new Response(true);
                    }
                    throw new \Plugin\ws5_mollie\lib\Exception\APIException('Gastbestellung noch nicht komplett versendet!');
            }

        } else {
            throw new \Plugin\ws5_mollie\lib\Exception\APIException('Bestellung konnte nicht geladen werden');
        }


        return new Response($shipment);

    }

}