<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Plugin\Helper;
use JTL\Shop;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Response;

class MollieController extends AbstractController
{

    /**
     * @param \stdClass $data
     * @return Response
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public static function methods(\stdClass $data)
    {
        $test = false;
        if (self::Plugin()->getConfig()->getValue('apiKey') === '' && self::Plugin()->getConfig()->getValue('test_apiKey') !== '') {
            $test = true;
        }
        $_methods = MollieAPI::API($test)->methods->allActive(['includeWallets' => 'applepay']);
        $methods = [];
        $oPlugin = self::Plugin();

        foreach ($_methods as $method) {
            $id = 'kPlugin_' . Helper::getIDByPluginID("ws5_mollie") . '_' . $method->id;
            $oZahlungsart = Shop::Container()->getDB()->executeQueryPrepared("SELECT * FROM tzahlungsart WHERE cModulId = :cModulID;", [
                ':cModulID' => $id
            ], 1);

            $methods[$method->id] = (object)[
                'settings' => Shop::getURL() . "/admin/zahlungsarten.php?kZahlungsart={$oZahlungsart->kZahlungsart}&token={$_SESSION['jtl_token']}",
                'mollie' => $method,
                'duringCheckout' => (int)$oZahlungsart->nWaehrendBestellung === 1,
                'shipping' => \Shop::Container()->getDB()->executeQueryPrepared("SELECT v.* FROM tversandart v
JOIN tversandartzahlungsart vz ON v.kVersandart = vz.kVersandart
JOIN tzahlungsart z ON vz.kZahlungsart = z.kZahlungsart
WHERE z.cModulId = :cModulID", [':cModulID' => $id], 2),
            ];

            if ($api = $oPlugin->getConfig()->getValue($id . '_api')) {
                $methods[$method->id]->api = $api;
            }
            if ($api = $oPlugin->getConfig()->getValue($id . '_components')) {
                $methods[$method->id]->components = $api;
            }

        }

        return new Response($methods);
    }

    /**
     * @param \stdClass $data
     * @return Response
     */
    public static function statistics(\stdClass $data)
    {

        $id = 'kPlugin_' . Helper::getIDByPluginID("ws5_mollie") . '_%';

        $result = \Shop::Container()->getDB()->executeQueryPrepared('(
SELECT COUNT(b.cBestellNr) as transactions, ROUND(IFNULL(SUM(b.fGesamtsumme),0),2) as amount, "day" as timespan FROM tbestellung b
WHERE kZahlungsart IN (SELECT z.kZahlungsart FROM tzahlungsart z
WHERE z.cModulId LIKE :cModulId1)
AND b.dErstellt > DATE_SUB(CURDATE(), INTERVAL 1 DAY)
) UNION (
SELECT COUNT(b.cBestellNr) as transactions, ROUND(IFNULL(SUM(b.fGesamtsumme),0),2) as amount, "week" as timespan FROM tbestellung b
WHERE kZahlungsart IN (SELECT z.kZahlungsart FROM tzahlungsart z
WHERE z.cModulId LIKE :cModulId2)
AND b.dErstellt > DATE_SUB(CURDATE(), INTERVAL 1 WEEK)
) UNION (
SELECT COUNT(b.cBestellNr) as transactions, ROUND(IFNULL(SUM(b.fGesamtsumme),0),2) as amount, "month" as timespan FROM tbestellung b
WHERE kZahlungsart IN (SELECT z.kZahlungsart FROM tzahlungsart z
WHERE z.cModulId LIKE :cModulId3)
AND b.dErstellt > DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
) UNION (
SELECT COUNT(b.cBestellNr) as transactions, ROUND(IFNULL(SUM(b.fGesamtsumme),0),2) as amount, "year" as timespan FROM tbestellung b
WHERE kZahlungsart IN (SELECT z.kZahlungsart FROM tzahlungsart z
WHERE z.cModulId LIKE :cModulId4)
AND b.dErstellt > DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
)', [
            ':cModulId1' => $id,
            ':cModulId2' => $id,
            ':cModulId3' => $id,
            ':cModulId4' => $id,

        ], 2);

        $response = array_combine(array_map(function ($v) {
            return $v->timespan;
        }, $result), array_values($result));

        return new Response($response);

    }

}