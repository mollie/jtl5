<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Plugin\Helper;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Response;

class MollieController extends AbstractController
{

    public static function methods(\stdClass $data)
    {

        $_methods = MollieAPI::API()->methods->allActive(['includeWallets'=>'applepay']);
        $methods = [];
        foreach ($_methods as $method) {
            $id = 'kPlugin_' . Helper::getIDByPluginID("ws5_mollie") . '_'. ($method->id === 'creditcard' ? 'kreditkarte' : $method->id);
            $methods[$method->id] = (object)[
                'mollie' => $method,
                'shipping' => \Shop::Container()->getDB()->executeQueryPrepared("SELECT * FROM tversandart v
JOIN tversandartzahlungsart vz ON v.kVersandart = vz.kVersandart
JOIN tzahlungsart z ON vz.kZahlungsart = z.kZahlungsart
WHERE z.cModulId = :cModulID", [':cModulID' => $id], 2),
            ];
        }

        return new Response($methods);
    }

}