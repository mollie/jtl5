<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib;

require_once __DIR__ . '/../vendor/autoload.php';

use Exception;
use JTL\Alert\Alert;
use JTL\Checkout\Bestellung;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Plugin\Helper as JtlPluginHelper;
use JTL\Plugin\Payment\Method;
use JTL\Session\Frontend;
use JTL\Shop;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Plugin\ws5_mollie\lib\Checkout\OrderCheckout;
use Plugin\ws5_mollie\lib\Checkout\PaymentCheckout;
use WS\JTL5\V1_0_16\Traits\Plugins;

abstract class PaymentMethod extends Method
{
    use Plugins;

    public const ALLOW_AUTO_STORNO = true;

    public const ALLOW_PAYMENT_BEFORE_ORDER = false;

    public const METHOD = '';

    /**
     * @var int
     */
    protected $kPlugin;

    /**
     * @param int $nAgainCheckout
     *
     * @return static
     */
    public function init(int $nAgainCheckout = 0)
    {
        parent::init($nAgainCheckout);

        $this->kPlugin = JtlPluginHelper::getIDByModuleID($this->moduleID);

        return $this;
    }

    /**
     * @return true
     */
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
            $selectable = trim(PluginHelper::getSetting('test_apiKey')) !== '';
        } else {
            $selectable = trim(PluginHelper::getSetting('apiKey')) !== '';
            if (!$selectable) {
                $this->doLog('Live API Key missing!', LOGLEVEL_ERROR);
            }
        }
        if ($selectable) {
            try {
                $locale = self::getLocale(Frontend::getInstance()->getLanguage()->gibISO(), Frontend::getCustomer()->cLand);
                $amount = Frontend::getCart()->gibGesamtsummeWaren(true) * Frontend::getCurrency()->getConversionFactor();
                if ($amount <= 0) {
                    $amount = 0.01;
                }
                $selectable = self::isMethodPossible(
                    static::METHOD,
                    $locale,
                    Frontend::getCustomer()->cLand,
                    Frontend::getCurrency()->getCode(),
                    $amount
                );
            } catch (Exception $e) {
                $selectable = false;
            }
        }

        return $selectable && parent::isSelectable();
    }

    /**
     * @param string      $cISOSprache
     * @param null|string $country
     * @return string
     */
    public static function getLocale(string $cISOSprache, string $country = null): string
    {
        switch ($cISOSprache) {
            case 'ger':
                if ($country === 'AT') {
                    return 'de_AT';
                }
                if ($country === 'CH') {
                    return 'de_CH';
                }
                return 'de_DE';
            case 'fre':
                if ($country === 'BE') {
                    return 'fr_BE';
                }
                return 'fr_FR';
            case 'dut':
                if ($country === 'BE') {
                    return 'nl_BE';
                }
                return 'nl_NL';
            case 'spa':
                return 'es_ES';
            case 'ita':
                return 'it_IT';
            case 'pol':
                return 'pl_PL';
            case 'hun':
                return 'hu_HU';
            case 'por':
                return 'pt_PT';
            case 'nor':
                return 'nb_NO';
            case 'swe':
                return 'sv_SE';
            case 'fin':
                return 'fi_FI';
            case 'dan':
                return 'da_DK';
            case 'ice':
                return 'is_IS';
            default:
                return 'en_US';
        }
    }

    /**
     * @param $method
     * @param string $locale
     * @param $billingCountry
     * @param $currency
     * @param $amount
     * @throws IncompatiblePlatform
     * @throws ApiException
     * @return bool
     */
    protected static function isMethodPossible($method, string $locale, $billingCountry, $currency, $amount): bool
    {
        $api = new MollieAPI(MollieAPI::getMode());

        if (!array_key_exists('mollie_possibleMethods', $_SESSION)) {
            //Frontend::set('mollie_possibleMethods', []);
            $_SESSION['mollie_possibleMethods'] = [];
        }

        $key = md5(serialize([$locale, $billingCountry, $currency, $amount]));
        if (!array_key_exists($key, $_SESSION['mollie_possibleMethods'])) {
            $active = $api->getClient()->methods->allActive([
                'locale' => $locale,
                'amount' => [
                    'currency' => $currency,
                    'value'    => number_format($amount, 2, '.', '')
                ],
                'billingCountry' => $billingCountry,
                'resource'       => 'orders',
                'includeWallets' => 'applepay',
            ]);
            foreach ($active as $a) {
                $_SESSION['mollie_possibleMethods'][$key][] = (object)['id' => $a->id];
            }
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
            if ($this->duringCheckout && !static::ALLOW_PAYMENT_BEFORE_ORDER) {
                $this->doLog('Zahlung vor Bestellabschluss nicht unterstützt!', LOGLEVEL_ERROR);

                return;
            }

            $payable = (float)$order->fGesamtsumme > 0;
            if (!$payable) {
                $this->doLog(sprintf("Bestellung '%s': Gesamtsumme %.2f, keine Zahlung notwendig!", $order->cBestellNr, $order->fGesamtsumme), LOGLEVEL_NOTICE);

                return;
            }

            $paymentOptions = [];

            if (Frontend::getCustomer()->nRegistriert && ($customerID = Customer::createOrUpdate(Frontend::getCustomer()))) {
                $paymentOptions['customerId'] = $customerID;
            }

            // TODO: Refactor this to use "PluginHelper::getPaymentSetting" once available
            $api = self::Plugin('ws5_mollie')->getConfig()->getValue($this->moduleID . '_api');

            $paymentOptions = array_merge($paymentOptions, $this->getPaymentOptions($order, $api));

            if ($api === 'payment') {
                $checkout = PaymentCheckout::factory($order);
                $payment = $checkout->create($paymentOptions);
                $url = $payment->getCheckoutUrl();
            } else {
                $checkout = OrderCheckout::factory($order);
                $mOrder = $checkout->create($paymentOptions);
                $url = $mOrder->getCheckoutUrl();
            }

            try {
                if ($order->kBestellung > 0 && method_exists($this, 'generatePUI') && ($pui = $this->generatePUI($checkout))) {
                    $order->cPUIZahlungsdaten = $pui;
                    $order->updateInDB();
                }
            } catch (\Exception $e) {
                $this->doLog('mollie::preparePaymentProcess: PUI - ' . $e->getMessage() . ' - ' . print_r(['cBestellNr' => $order->cBestellNr], 1), LOGLEVEL_NOTICE);
            }

            if ($url) {
                ifndef('MOLLIE_REDIRECT_DELAY', 3);
                $checkoutMode = PluginHelper::getSetting('checkoutMode');
                Shop::Smarty()->assign('redirect', $url)
                    ->assign('checkoutMode', $checkoutMode);
                if ($checkoutMode === 'Y' && !headers_sent()) {
                    header('Location: ' . $url);
                    ifndef('MOLLIE_STOP_EXEC_AFTER_WEBHOOK', true);
                    if (MOLLIE_STOP_EXEC_AFTER_WEBHOOK) {
                        exit();
                    }
                }
            }
        } catch (Exception $e) {
            $this->doLog('mollie::preparePaymentProcess: ' . $e->getMessage() . ' - ' . print_r(['cBestellNr' => $order->cBestellNr], 1), LOGLEVEL_ERROR);

            Shop::Container()->getAlertService()->addAlert(
                Alert::TYPE_ERROR,
                PluginHelper::getPlugin()->getLocalization()->getTranslation('error_create'),
                'paymentFailed'
            );
        }
    }

    abstract public function getPaymentOptions(Bestellung $order, $apiType): array;

    /**
     * @param Bestellung $order
     * @param string     $hash
     * @param array      $args
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function handleNotification(Bestellung $order, string $hash, array $args): void
    {
        parent::handleNotification($order, $hash, $args);

        try {
            $orderId = $args['id'];
            if (strpos($orderId, 'tr_') === 0) {
                $checkout = PaymentCheckout::factory($order);
            } else {
                $checkout = OrderCheckout::factory($order);
            }
            $checkout->handleNotification($hash);
        } catch (Exception $e) {
            $this->doLog("ERROR: mollie::handleNotification: Bestellung '$order->cBestellNr': {$e->getMessage()}", LOGLEVEL_ERROR);
            Shop::Container()->getBackendLogService()->critical($e->getMessage(), $_REQUEST);
        }
    }
}
