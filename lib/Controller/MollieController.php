<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use Exception;
use JTL\Catalog\Currency;
use JTL\DB\ReturnType;
use JTL\Plugin\Helper;
use JTL\Plugin\Payment\LegacyMethod;
use JTL\Shop;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatformException;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\PaymentMethod;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\PluginHelper;
use RuntimeException;
use stdClass;
use WS\JTL5\V2_1_4\Backend\AbstractResult;
use WS\JTL5\V2_1_4\Backend\Controller\AbstractController;

class MollieController extends AbstractController
{
    /**
     * @param stdClass $data
     * @return AbstractResult
     * @throws ApiException
     * @throws IncompatiblePlatformException
     */
    public static function methods(stdClass $data): AbstractResult
    {
        if (PluginHelper::getSetting('apiKey') === '' && PluginHelper::getSetting('test_apiKey') === '') {
            return new AbstractResult((object)[
                'reason' => 'missing_api_key',
                'methods' => new stdClass(),
            ]);
        }


        $test = false;
        if (PluginHelper::getSetting('apiKey') === '' && PluginHelper::getSetting('test_apiKey') !== '') {
            $test = true;
        }
        $api = new MollieAPI($test);

        $_methods_arr = [];
        try {
            // Get methods for default currency EUR
            try {
                $_methods = $api->getClient()->methods->allEnabled([
                    'includeWallets' => ['applepay'],
                    'amount' => [
                        'value' => '50.00',
                        'currency' => 'EUR',
                    ],
                ]);
                $_methods_arr['EUR'] = $_methods;
            } catch (\Exception $e) {
                PluginHelper::getLogger()->error('Error while fetching methods from mollie for currency EUR. Message: ' . $e->getMessage());
            }


            // Get methods for all other active currencies
            $currencies = Currency::loadAll();
            if (is_array($currencies) && count($currencies) > 0) {
                foreach ($currencies as $currency) {
                    if ($currency->getCode() !== 'EUR') {
                        try {
                            $_methods = $api->getClient()->methods->allEnabled(
                                [
                                    'includeWallets' => ['applepay'],
                                    'amount' => [
                                        'value' => '10.00',
                                        'currency' => $currency->getCode(),
                                    ],
                                ]
                            );
                            if ($_methods->count() > 0) {
                                $_methods_arr[$currency->getCode()] = $_methods;
                            }
                        } catch (\Exception $e) {
                            PluginHelper::getLogger()->error('Error while fetching methods from mollie for currency ' . $currency->getCode() . '. Message: ' . $e->getMessage());
                        }
                    }
                }
            }

            $methods = [];
            $oPlugin = self::Plugin('ws5_mollie');

            foreach ($_methods_arr as $iso => $_methods) {
                foreach ($_methods as $method) {
                    if (in_array($method->id, ['voucher', PaymentMethod::DIRECTDEBIT, PaymentMethod::GIFTCARD], true)) {
                        continue;
                    }

                    // Merge different currencies: add currency if method already exist and continue with next element
                    if (array_key_exists($method->id, $methods)) {
                        $methods[$method->id]->currencies[] = $iso;
                        continue;
                    }

                    $id = 'kPlugin_' . Helper::getIDByPluginID('ws5_mollie') . '_' . $method->id;
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
                        'currencies' => [$iso]
                    ];

                    if ($api = $oPlugin->getConfig()->getValue($id . '_components')) {
                        $methods[$method->id]->components = $api;
                    }
                    if ($dueDays = $oPlugin->getConfig()->getValue($id . '_dueDays')) {
                        $methods[$method->id]->dueDays = (int)$dueDays;
                    }
                }
            }

            $reason = count($methods) > 0 ? 'ok' : 'no_methods';

            return new AbstractResult((object)[
                'reason' => $reason,
                'methods' => (object)$methods,
            ]);
        } catch (\Exception $e) {
            PluginHelper::getLogger()->error('Error while fetching methods from mollie. Message: ' . $e->getMessage());
            return new AbstractResult((object)[
                'reason' => 'error',
                'methods' => new stdClass(),
                'message' => $e->getMessage(),
            ]);
        }
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
     * @return AbstractResult
     * @throws ApiException
     * @throws Exception
     */
    public static function cancelOrderLine(stdClass $data): AbstractResult
    {
        throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE . ' Line-Cancel ist nicht mehr verfügbar.');
    }

    /**
     * @throws ApiException
     * @throws Exception
     */
    public static function cancelOrder(stdClass $data): AbstractResult
    {
        $checkout = AbstractCheckout::fromID($data->id);
        /** @var PaymentCheckout $checkout */
        $payment = $checkout->getMollie();
        if ($payment === null) {
            throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE);
        }

        if ($payment->isCancelable) {
            $res = $checkout->getAPI()->getClient()->payments->cancel($payment->id);

            return new AbstractResult($res->isCanceled());
        }
        if ($payment->isAuthorized() && $payment->captureMode === 'manual') {
            $checkout->releaseAuthorization();

            return new AbstractResult(true);
        }

        throw new RuntimeException('Payment kann nicht storniert werden (Status: ' . $payment->status . ').');
    }

    /**
     * @throws ApiException
     * @throws Exception
     */
    public static function refundOrder(stdClass $data): AbstractResult
    {
        $checkout = AbstractCheckout::fromID($data->id);
        /** @var PaymentCheckout $checkout */
        $payment = $checkout->getMollie();
        if ($payment === null) {
            throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE);
        }
        if (empty($payment->amountRemaining)) {
            throw new RuntimeException('Payment kann nicht erstattet werden (kein amountRemaining).');
        }

        $payment->refund([
            'amount' => [
                'currency' => $payment->amountRemaining->currency,
                'value' => $payment->amountRemaining->value,
            ],
        ]);

        return new AbstractResult(true);
    }

    /**
     * @throws ApiException
     * @throws Exception
     */
    public static function cancelRefund(stdClass $data): AbstractResult
    {
        if (!$data->id || !$data->refundId) {
            throw new RuntimeException('Missing Mollie ID or Refund ID!');
        }

        $checkout = AbstractCheckout::fromID($data->id);
        $payment = $checkout->getMollie();
        if ($payment === null) {
            throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE);
        }

        $refunds = $payment->refunds();
        /** @var Refund $refund */
        foreach ($refunds as $refund) {
            if ($refund->id === $data->refundId) {
                $refund->cancel();

                return new AbstractResult(true);
            }
        }

        throw new RuntimeException('Refund not found!');
    }

    /**
     * @throws ApiException
     * @throws Exception
     */
    public static function refundOrderLine(stdClass $data): AbstractResult
    {
        throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE . ' Line-Refund ist nicht mehr verfügbar.');
    }


    /**
     * @throws ApiException
     * @throws Exception
     */
    public static function refundAmount(stdClass $data): AbstractResult
    {
        $checkout = AbstractCheckout::fromID($data->id);
        /** @var PaymentCheckout $checkout */

        if (!$data->amount) {
            throw new RuntimeException('Invalid Amount!');
        }

        $payment = $checkout->getMollie();
        if ($payment === null) {
            throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE);
        }

        $result = $payment->refund([
            'amount' => [
                'value' => number_format((float)$data->amount, 2, '.', ''),
                'currency' => $payment->amount->currency,
            ],
            'description' => 'Refund for order ' . $checkout->getBestellung()->cBestellNr,
        ]);

        return new AbstractResult($result->id);
    }

    /**
     * @throws Exception
     */
    public static function getOrder(stdClass $data)
    {
        // Legacy ord_* → Payment via cTransactionId (Payment API only)
        $checkout = AbstractCheckout::fromID($data->id);
        $payment = $checkout->getMollie();
        if ($payment === null) {
            throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE);
        }

        return new AbstractResult($payment);
    }

    /**
     * @throws Exception
     */
    public static function getPayment(stdClass $data)
    {
        $checkout = AbstractCheckout::fromID($data->id);
        $payment = $checkout->getMollie();
        if ($payment === null) {
            throw new RuntimeException(AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE);
        }

        return new AbstractResult($payment);
    }

    public static function releaseAuthorization(stdClass $data): AbstractResult
    {
        $checkout = AbstractCheckout::fromID($data->id);
        /** @var PaymentCheckout $checkout */

        return new AbstractResult($checkout->releaseAuthorization());
    }
}
