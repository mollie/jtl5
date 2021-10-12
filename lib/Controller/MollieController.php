<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use JTL\Plugin\Helper;
use JTL\Plugin\Payment\LegacyMethod;
use JTL\Shop;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Types\PaymentMethod;
use Plugin\ws5_mollie\lib\MollieAPI;
use stdClass;
use WS\JTL5\Backend\AbstractResult;
use WS\JTL5\Backend\Controller\AbstractController;

class MollieController extends AbstractController
{
    /**
     * @param stdClass $data
     * @throws IncompatiblePlatform
     * @throws ApiException
     * @return AbstractResult
     */
    public static function methods(stdClass $data): AbstractResult
    {
        $test = false;
        if (self::Plugin('ws5_mollie')->getConfig()->getValue('apiKey') === '' && self::Plugin('ws5_mollie')->getConfig()->getValue('test_apiKey') !== '') {
            $test = true;
        }
        $api = new MollieAPI($test);

        $_methods = $api->getClient()->methods->allAvailable([/*'includeWallets' => 'applepay', 'resource' => 'orders'*/]);
        $methods  = [];
        $oPlugin  = self::Plugin('ws5_mollie');

        foreach ($_methods as $method) {
            if (in_array($method->id, ['voucher', PaymentMethod::DIRECTDEBIT, PaymentMethod::GIFTCARD], true)) {
                continue;
            }
            $id           = 'kPlugin_' . Helper::getIDByPluginID('ws5_mollie') . '_' . $method->id;
            $oZahlungsart = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM tzahlungsart WHERE cModulId = :cModulID;', [
                ':cModulID' => $id
            ], 1);

            $oPaymentMethod = LegacyMethod::create($oZahlungsart->cModulId);

            $methods[$method->id] = (object)[
                'log'                 => Shop::Container()->getDB()->getAffectedRows('SELECT * FROM tzahlungslog WHERE cModulId = :cModulId AND dDatum < DATE_SUB(NOW(), INTERVAL 30 DAY)', [':cModulId' => $oZahlungsart->cModulId]),
                'settings'            => Shop::getURL() . "/admin/zahlungsarten.php?kZahlungsart=$oZahlungsart->kZahlungsart&token={$_SESSION['jtl_token']}",
                'mollie'              => $method,
                'duringCheckout'      => (int)$oZahlungsart->nWaehrendBestellung === 1,
                'allowDuringCheckout' => $oPaymentMethod::ALLOW_PAYMENT_BEFORE_ORDER ?? null,
                'paymentMethod'       => $oZahlungsart,
                'shipping'            => Shop::Container()->getDB()->executeQueryPrepared('SELECT v.* FROM tversandart v
JOIN tversandartzahlungsart vz ON v.kVersandart = vz.kVersandart
JOIN tzahlungsart z ON vz.kZahlungsart = z.kZahlungsart
WHERE z.cModulId = :cModulID', [':cModulID' => $id], 2),
            ];

            if ($api = $oPlugin->getConfig()->getValue($id . '_api')) {
                $methods[$method->id]->api = $api;
            }
            if ($api = $oPlugin->getConfig()->getValue($id . '_components')) {
                $methods[$method->id]->components = $api;
            }
            if ($dueDays = $oPlugin->getConfig()->getValue($id . '_dueDays')) {
                $methods[$method->id]->dueDays = (int)$dueDays;
            }
        }

        return new AbstractResult($methods);
    }

    /**
     * @param stdClass $data
     * @return AbstractResult
     */
    public static function cleanlog(stdClass $data): AbstractResult
    {
        if (isset($data->cModulId) && ($modulId = $data->cModulId)) {
            return new AbstractResult(Shop::Container()->getDB()->delete('tzahlungslog', 'cModulId', $modulId));
        }

        return new AbstractResult(false);
    }

    /**
     * @param stdClass $data
     * @return AbstractResult
     */
    public static function statistics(stdClass $data): AbstractResult
    {
        $id = 'kPlugin_' . Helper::getIDByPluginID('ws5_mollie') . '_%';

        $result = Shop::Container()->getDB()->executeQueryPrepared('(
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

        $response = array_combine(array_map(static function ($v) {
            return $v->timespan;
        }, $result), array_values($result));

        return new AbstractResult($response);
    }
}
