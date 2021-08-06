<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use Kunde;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Response;
use Plugin\ws5_mollie\lib\Shipment;
use stdClass;
use WS\JTL5\Backend\Controller\AbstractController;
use WS\JTL5\Exception\APIException;

class ShipmentsController extends AbstractController
{
    /**
     * @param stdClass $data
     * @throws IncompatiblePlatform
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Response
     */
    public static function sync(stdClass $data): Response
    {
        if (!$data->kBestellung || !$data->kLieferschein || !$data->orderId) {
            throw new APIException('Bestellung, Liefererschien oder Mollie OrderId fehlen.');
        }

        $checkout = OrderCheckout::fromID($data->orderId);

        if ($checkout->getModel()->kBestellung) {
            $shipment = new Shipment((int)$data->kLieferschein, $checkout);

            $oKunde = new Kunde($checkout->getBestellung()->kKunde);

            $mode = self::Plugin('ws5_mollie')->getConfig()->getValue('shippingMode');
            switch ($mode) {
                case 'A':
                    // ship directly
                    if (!$shipment->send() && !$shipment->getShipment()) {
                        throw new APIException('Shipment konnte nicht gespeichert werden.');
                    }

                    return new Response(true);
                case 'B':
                    // only ship if complete shipping
                    if ($oKunde->nRegistriert || (int)$checkout->getBestellung()->cStatus === BESTELLUNG_STATUS_VERSANDT) {
                        if (!$shipment->send() && !$shipment->getShipment()) {
                            throw new APIException('Shipment konnte nicht gespeichert werden.');
                        }

                        return new Response(true);
                    }

                    throw new APIException('Gastbestellung noch nicht komplett versendet!');
            }
        } else {
            throw new APIException('Bestellung konnte nicht geladen werden');
        }

        return new Response($shipment);
    }
}
