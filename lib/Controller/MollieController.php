<?php


namespace Plugin\ws5_mollie\lib\Controller;


use JTL\Plugin\Helper;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Response;

class MollieController extends AbstractController
{

    public static function methods(\stdClass $data)
    {

        $_methods = MollieAPI::API()->methods->allActive(['includeWallets' => 'applepay']);
        $methods = [];
        foreach ($_methods as $method) {
            $id = 'kPlugin_' . Helper::getIDByPluginID("ws5_mollie") . '_' . ($method->id === 'creditcard' ? 'kreditkarte' : $method->id);
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