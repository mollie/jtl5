<?php

/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib;

use Exception;
use JTL\Alert\Alert;
use JTL\Checkout\Bestellung;
use JTL\Checkout\Zahlungsart;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Model\DataModel;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\Payment\MethodInterface;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Traits\Plugin;
use Plugin\ws5_mollie\paymentmethod\CreditCard;
use RuntimeException;
use Session;
use Shop;
use stdClass;

class PaymentMethod extends Method
{

    public const ALLOW_PAYMENT_BEFORE_ORDER = false;

    public const METHOD = '';

    /**
     * @var string
     */
    protected $pluginID;

    use Plugin;

    /**
     * @param int $nAgainCheckout
     * @return $this|Method|MethodInterface|PaymentMethod
     */
    public function init($nAgainCheckout = 0)
    {
        parent::init($nAgainCheckout);

        $this->pluginID = PluginHelper::getIDByModuleID($this->moduleID);

        return $this;
    }

    public function canPayAgain(): bool
    {
        return true;
    }

    /**
     * @param array $args_arr
     * @return bool
     */
    public function isValidIntern(array $args_arr = []): bool
    {
        return $this->duringCheckout
            ? static::ALLOW_PAYMENT_BEFORE_ORDER && parent::isValidIntern($args_arr)
            : parent::isValidIntern($args_arr);
    }

    /**
     * @return bool
     */
    public function isSelectable(): bool
    {
        if (MollieAPI::getMode()) {
            $selectable = trim(self::Plugin()->getConfig()->getValue('test_apiKey')) !== '';
        } else {
            $selectable = trim(self::Plugin()->getConfig()->getValue('apiKey')) !== '';
            if (!$selectable) {
                $this->doLog("Live API Key missing!", LOGLEVEL_ERROR);
            }
        }
        if ($selectable) {
            try {
                $locale = self::getLocale($_SESSION['cISOSprache'], $_SESSION['Kunde']->cLand);
                $amount = Session::getCart()->gibGesamtsummeWaren(true) * Session::getCurrency()->getConversionFactor();
                if ($amount <= 0) {
                    $amount = 0.01;
                }
                $selectable = self::isMethodPossible(
                    static::METHOD,
                    $locale,
                    Session::getCustomer()->cLand,
                    Session::getCurrency()->getCode(),
                    $amount
                );
            } catch (Exception $e) {
                $selectable = false;
            }
        }
        return $selectable && parent::isSelectable();
    }

    /**
     * @param string $cISOSprache
     * @param string|null $country
     * @return string
     */
    public static function getLocale(string $cISOSprache, string $country = null): string
    {
        switch ($cISOSprache) {
            case "ger":
                if ($country === "AT") {
                    return "de_AT";
                }
                if ($country === "CH") {
                    return "de_CH";
                }
                return "de_DE";
            case "fre":
                if ($country === "BE") {
                    return "fr_BE";
                }
                return "fr_FR";
            case "dut":
                if ($country === "BE") {
                    return "nl_BE";
                }
                return "nl_NL";
            case "spa":
                return "es_ES";
            case "ita":
                return "it_IT";
            case "pol":
                return "pl_PL";
            case "hun":
                return "hu_HU";
            case "por":
                return "pt_PT";
            case "nor":
                return "nb_NO";
            case "swe":
                return "sv_SE";
            case "fin":
                return "fi_FI";
            case "dan":
                return "da_DK";
            case "ice":
                return "is_IS";
            default:
                return "en_US";
        }
    }

    /**
     * @param $method
     * @param $locale
     * @param $billingCountry
     * @param $currency
     * @param $amount
     * @return bool
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    protected static function isMethodPossible($method, $locale, $billingCountry, $currency, $amount): bool
    {

        if (!array_key_exists('mollie_possibleMethods', $_SESSION)) {
            $_SESSION['mollie_possibleMethods'] = [];
        }

        $key = md5(serialize([$locale, $billingCountry, $currency, $amount]));
        if (!array_key_exists($key, $_SESSION['mollie_possibleMethods'])) {
            $_SESSION['mollie_possibleMethods'][$key] = MollieAPI::API(MollieAPI::getMode())->methods->allActive([
                'locale' => $locale,
                'amount' => [
                    'currency' => $currency,
                    'value' => number_format($amount, 2, ".", "")
                ],
                'billingCountry' => $billingCountry,
                'resource' => 'orders',
                'includeWallets' => 'applepay',
            ]);
        }

        if ($method !== '') {
            foreach ($_SESSION['mollie_possibleMethods'][$key] as $m) {
                if ($m->id === $method) {
                    return true;
                }
            }
        } else {
            return true;
        }

        return false;

    }

    /**
     * @param Bestellung $order
     */
    public function preparePaymentProcess(Bestellung $order): void
    {

        parent::preparePaymentProcess($order);

        try {
            $payable = (float)$order->fGesamtsumme > 0;
            if (!$payable) {
                if ($this->duringCheckout) {
                    $this->doLog("Zahlung vor Bestellabschluss nicht unterstützt!", LOGLEVEL_ERROR);
                } else {
                    return;
                }
            }

            $paymentOptions = [];

            if ((int)Session::getCustomer()->nRegistriert) {
                $paymentOptions['customerId'] = Customer::createOrUpdate(Session::getCustomer());
            }

            if (static::METHOD === \Mollie\Api\Types\PaymentMethod::CREDITCARD) {
                if ((int)$this->getCache(CreditCard::CACHE_TOKEN_TIMESTAMP) > time() && ($token = trim($this->getCache(CreditCard::CACHE_TOKEN)))) {
                    $paymentOptions['cardToken'] = $token;
                }
            }

            if ($this->duringCheckout) {
                $this->doLog("Zahlung vor Bestellabschluss nicht unterstützt!", LOGLEVEL_ERROR);
            } else if ($mollieOrder = Order::createOrder($order, $paymentOptions)) {

                $this->handleNotification($order, $mollieOrder->metadata->cHash, ['id' => $mollieOrder->id]);

                if (!headers_sent()) {
                    header('Location: ' . $mollieOrder->getCheckoutUrl());
                }
                Shop::Smarty()->assign('redirect', $mollieOrder->getCheckoutUrl());

            } else {

                throw new RuntimeException('Order konnte bei mollie nicht erstellt werden! ' . print_r([$order->cBestellNr, $paymentOptions], 1));

            }
        } catch (Exception $e) {

            $this->doLog('mollie::preparePaymentProcess: ' . $e->getMessage() . ' - ' . print_r(['cBestellNr' => $order->cBestellNr], 1), LOGLEVEL_ERROR);

            Shop::Container()->getAlertService()->addAlert(
                Alert::TYPE_ERROR,
                self::Plugin()->getLocalization()->getTranslation("error_create"),
                'paymentFailed'
            );
        }
    }

    /**
     * @param Bestellung $order
     * @param string $hash
     * @param array $args
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function handleNotification(Bestellung $order, string $hash, array $args): void
    {
        parent::handleNotification($order, $hash, $args);

        try {

            $orderId = $args['id'];

            $orderModel = OrderModel::loadByAttributes(
                ['orderId' => $orderId, 'hash' => $hash],
                Shop::Container()->getDB(),
                DataModel::ON_NOTEXISTS_NEW);

            $mOrder = MollieAPI::API($orderModel->getTest())->orders->get($orderId, ['embed' => 'payments']);

            Order::update($mOrder);

            if ((null === $order->dBezahltDatum) && (list($payValue, $payment) = $this->updateOrder((int)$order->kBestellung, $orderModel, $mOrder)) && $payment) {

                $this->addIncomingPayment($order, (object)[
                    'fBetrag' => $payment->amount->value,
                    'cISO' => $payment->amount->currency,
                    'cZahler' => $payment->details->paypalPayerId ?? $payment->customerId,
                    'cHinweis' => $payment->details->paypalReference ?? $mOrder->id,
                ]);

                // If totally paid, mark as paid, make fetchable by WAWI and delete Hash
                if ($payValue >= $order->fGesamtsumme) {
                    $this->setOrderStatusToPaid($order);
                    self::makeFetchable($order, $orderModel);
                    $this->deletePaymentHash($hash);

                    $oZahlungsart = Shop::Container()->getDB()->selectSingleRow('tzahlungsart', 'cModulId', $this->moduleID);
                    if ($oZahlungsart && (int)$oZahlungsart->nMailSenden === 1) {
                        $this->sendConfirmationMail($order);
                    }

                } else {
                    $this->doLog("Bestellung '{$order->cBestellNr}': Betrag zu niedrig {$payValue}", LOGLEVEL_NOTICE);
                }
            } else {
                $this->doLog("Bestellung '{$order->cBestellNr}' bereits als bezahlt markiert.");
            }

        } catch (Exception $e) {
            $this->doLog("mollie::handleNotification: Bestellung '{$order->cBestellNr}': {$e->getMessage()}", LOGLEVEL_ERROR);
            Shop::Container()->getBackendLogService()->addCritical($e->getMessage(), $_REQUEST);
        }
    }

    /**
     * @param int $kBestellung
     * @param OrderModel $order
     * @param \Mollie\Api\Resources\Order $mollie
     * @return array|null
     * @throws Exception
     */
    public function updateOrder(int $kBestellung, OrderModel $order, \Mollie\Api\Resources\Order $mollie): ?array
    {
        $payment = null;
        /** @var Payment $payment */
        /** @var Payment $_payment */
        $payValue = 0.0;
        foreach ($mollie->payments() as $_payment) {
            if (in_array($_payment->status,
                [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
                $payment = $_payment;
                $payValue += (float)$_payment->amount->value;
            }
        }
        $order->setBestellung($kBestellung);
        $order->setModified(date('Y-m-d H:i:s'));
        $order->setStatus($mollie->status);
        $order->setTransactionId($payment->id ?? '');
        $order->setThirdId($payment->details->paypalReference ?? '');
        $order->setMethod($mollie->method);
        $order->setAmount($mollie->amount->value);
        $order->setCurrency($mollie->amount->currency);
        $order->setAmountRefunded($mollie->amountRefunded->value ?? 0);
        $order->setLocale($mollie->locale);

        if ($order->save()) {
            return [$payValue, $payment];
        }
        return null;
    }

    /**
     * @param Bestellung $order
     * @param OrderModel $orderModel
     * @return bool
     * @throws Exception
     */
    public static function makeFetchable(Bestellung $order, OrderModel $orderModel): bool
    {

        if ($order->cAbgeholt === 'Y' && $orderModel->getSynced() === false) {
            $_upd = new stdClass();
            $_upd->cAbgeholt = 'N';
            if (\JTL\Shop::Container()->getDB()->update('tbestellung', 'kBestellung', (int)$order->kBestellung, $_upd)) {
                $orderModel->setSynced(true);
                return $orderModel->save();
            }
        }
        return false;
    }

    /**
     * @param array $post
     * @return bool
     */
    public function handleAdditional(array $post): bool
    {
        return parent::handleAdditional($post);
    }


}
