<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use JTL\DB\ReturnType;
use JTL\Plugin\Helper;
use JTL\Plugin\Payment\LegacyMethod;
use JTL\Shop;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\PaymentMethod;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\PluginHelper;
use stdClass;
use WS\JTL5\V1_0_16\Backend\AbstractResult;
use WS\JTL5\V1_0_16\Backend\Controller\AbstractController;

class MollieController extends AbstractController
{
    /**
     * @param stdClass $data
     * @return AbstractResult
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public static function methods(stdClass $data): AbstractResult
    {
        $test = false;
        if (PluginHelper::getSetting('apiKey') === '' && PluginHelper::getSetting('test_apiKey') !== '') {
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
            $oZahlungsart = PluginHelper::getDB()->executeQueryPrepared('SELECT * FROM tzahlungsart WHERE cModulId = :cModulID;', [
                ':cModulID' => $id
            ], 1);

            // If Mollie has new payment method that we don't support currently
            if (!$oZahlungsart) {
                continue;
            }

            $oPaymentMethod = LegacyMethod::create($oZahlungsart->cModulId);

            $methods[$method->id] = (object)[
                'log' => PluginHelper::getDB()->executeQueryPrepared('SELECT * FROM tzahlungslog WHERE cModulId = :cModulId AND dDatum < DATE_SUB(NOW(), INTERVAL 30 DAY)', [':cModulId' => $oZahlungsart->cModulId], ReturnType::AFFECTED_ROWS),
                'linkToSettingsPage' => Shop::Container()->getLinkService()->getStaticRoute('/admin/zahlungsarten.php') . "?kZahlungsart=$oZahlungsart->kZahlungsart&token={$_SESSION['jtl_token']}",
                'mollie' => $method,
                'duringCheckout' => (int)$oZahlungsart->nWaehrendBestellung === 1,
                'allowDuringCheckout' => $oPaymentMethod::ALLOW_PAYMENT_BEFORE_ORDER ?? null,
                'paymentMethod' => $oZahlungsart,
                'linkedShippingMethods' => PluginHelper::getDB()->executeQueryPrepared('SELECT v.* FROM tversandart v
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
            return new AbstractResult(PluginHelper::getDB()->delete('tzahlungslog', 'cModulId', $modulId));
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

        $result = PluginHelper::getDB()->executeQueryPrepared('(
SELECT COUNT(b.cBestellNr) as transactions, ROUND(IFNULL(SUM(b.fGesamtsumme),0),2) as amount, "day" as timespan FROM tbestellung b
WHERE kZahlungsart IN (SELECT z.kZahlungsart FROM tzahlungsart z
WHERE z.cModulId LIKE :cModulId1)
AND b.dErstellt > DATE_SUB(CURDATE(), INTERVAL 24 HOUR)
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

    /**
     * @param stdClass $data
     * @throws \Exception
     * @throws ApiException
     * @return AbstractResult
     */
    public static function cancelOrderLine(stdClass $data): AbstractResult
    {
        if (strpos($data->id, 'ord_') !== 0) {
            throw new \RuntimeException('Invalid Order ID!');
        }
        if (strpos($data->lineId, 'odl_') !== 0) {
            throw new \RuntimeException('Invalid Orderline ID!');
        }
        if (!$data->quantity || $data->quantity <= 0) {
            throw new \RuntimeException('Invalid Quantity!');
        }

        $checkout = OrderCheckout::fromID($data->id);
        $checkout->getMollie()->cancelLines([
            'lines' => [
                [
                    'id'       => $data->lineId,
                    'quantity' => $data->quantity,
                ],
            ],
        ]);

        return new AbstractResult(true);
    }

    public static function cancelOrder(stdClass $data): AbstractResult
    {
        if (strpos($data->id, 'ord_') !== 0) {
            throw new \RuntimeException('Invalid Order ID!');
        }

        $checkout = OrderCheckout::fromID($data->id);

        return new AbstractResult($checkout->getMollie()->cancel()->isCanceled());
    }

    /**
     * @throws ApiException
     * @throws \Exception
     */
    public static function refundOrder(stdClass $data): AbstractResult
    {
        if (strpos($data->id, 'tr_') !== false) {
            $checkout = PaymentCheckout::fromID($data->id);
            $checkout->getMollie()->refund([
                'amount' => $checkout->getMollie()->amountRemaining,
            ]);
        } else {
            $checkout = OrderCheckout::fromID($data->id);
            $checkout->getMollie()->refundAll();
        }

        return new AbstractResult(true);
    }

    public static function cancelRefund(stdClass $data): AbstractResult
    {
        if (!$data->id || !$data->refundId) {
            throw new \RuntimeException('Missing Mollie ID or Refund ID!');
        }

        if (strpos($data->id, 'tr_') !== false) {
            $checkout = PaymentCheckout::fromID($data->id);
        } else {
            $checkout = OrderCheckout::fromID($data->id);
        }
        $refunds = $checkout->getMollie()->refunds();
        /** @var Refund $refund */
        foreach ($refunds as $refund) {
            if ($refund->id === $data->refundId) {
                $refund->cancel();

                return new AbstractResult(true);
            }
        }

        throw new \RuntimeException('Refund not found!');
    }

    public static function refundOrderLine(stdClass $data): AbstractResult
    {
        if (strpos($data->id, 'ord_') !== 0) {
            throw new \RuntimeException('Invalid Order ID!');
        }
        if (strpos($data->lineId, 'odl_') !== 0) {
            throw new \RuntimeException('Invalid Order ID!');
        }
        if (!$data->quantity || $data->quantity <= 0) {
            throw new \RuntimeException('Invalid Quantity!');
        }

        $checkout = OrderCheckout::fromID($data->id);
        $checkout->getMollie()->refund([
            'lines' => [
                [
                    'id'       => $data->lineId,
                    'quantity' => $data->quantity,
                ],
            ],
        ]);

        return new AbstractResult(true);
    }


    public static function refundAmount(stdClass $data): AbstractResult
    {
        if (strpos($data->id, 'tr_') !== 0) {
            throw new \RuntimeException('Invalid Payment ID!');
        }

        if (!$data->amount) {
            throw new \RuntimeException('Invalid Amount!');
        }

        $checkout = PaymentCheckout::fromID($data->id);
        $result   = $checkout->getMollie()->refund([
            'amount' => [
                'value'    => number_format((float)$data->amount, 2),
                'currency' => $checkout->getMollie()->amount->currency,
            ],
            'description' => 'Refund for order ' . $checkout->getBestellung()->cBestellNr,
        ]);

        return new AbstractResult($result->id);
    }

    public static function getOrder(stdClass $data)
    {
        if (strpos($data->id, 'ord_') !== 0) {
            throw new \RuntimeException('Invalid Order ID!');
        }
        $checkout = OrderCheckout::fromID($data->id);

        return new AbstractResult($checkout->getMollie());
    }

    public static function getPayment(stdClass $data)
    {
        if (strpos($data->id, 'tr_') !== 0) {
            throw new \RuntimeException('Invalid Payment ID!');
        }
        $checkout = PaymentCheckout::fromID($data->id);

        return new AbstractResult($checkout->getMollie());
    }
}
