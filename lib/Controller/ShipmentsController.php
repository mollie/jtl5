<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Checkout\Bestellung;
use Plugin\ws5_mollie\lib\Response;
use Plugin\ws5_mollie\lib\Shipment;

class ShipmentsController extends AbstractController
{


    public static function sync(\stdClass $data)
    {

        if (!$data->kBestellung || !$data->kLieferschein || !$data->orderId) {
            throw new \Exception('Bestellung, Liefererschien oder Mollie OrderId  fehlen.');
        }



        if(($oBestellung = new Bestellung($data->kBestellung)) && $oBestellung->kBestellung){
            $shipment = Shipment::factory((int)$data->kLieferschein, $data->orderId);

            $oKunde = new \Kunde($oBestellung->kKunde);

            $mode = self::Plugin()->getConfig()->getValue('shippingMode');
            switch ($mode) {
                case 'A':
                    // ship direcly
                    // send
                    break;
                case 'B':
                    // only ship if complete shipping
                    if($oKunde->nRegistriert || $oBestellung->cStatus === BESTELLUNG_STATUS_VERSANDT){
                        // send
                    }else{

                    }
                    break;
            }

        }else{
            throw new \Exception('Bestellung konnte nicht geladen werden');
        }




        return new Response($shipment);

    }

}