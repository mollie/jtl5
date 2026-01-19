<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Checkout;

use Exception;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Artikel;
use JTL\Catalog\Product\EigenschaftWert;
use JTL\Catalog\Product\Preise;
use JTL\CheckBox;
use JTL\Checkout\Bestellung;
use JTL\Checkout\OrderHandler;
use JTL\Checkout\StockUpdater;
use JTL\Checkout\ZahlungsLog;
use JTL\Customer\Customer;
use JTL\DB\ReturnType;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Helpers\PaymentMethod;
use JTL\Helpers\Product;
use JTL\Mail\Mail\Mail;
use JTL\Mail\Mailer;
use JTL\Plugin\Payment\FallbackMethod;
use JTL\Plugin\Payment\LegacyMethod;
use JTL\Plugin\Payment\MethodInterface;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Shopsetting;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Locale;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Order\Amount;
use Plugin\ws5_mollie\lib\PluginHelper;
use Plugin\ws5_mollie\lib\Traits\RequestData;
use RuntimeException;
use stdClass;
use WS\JTL5\V2_0_5\Model\ModelInterface;
use WS\JTL5\V2_0_5\Traits\Plugins;

/**
 * Class AbstractCheckout
 * @package Plugin\ws5_mollie\lib\Checkout
 *
 * @property string $locale
 * @property Amount $amount
 * @property string $redirectUrl
 * @property null|array $metadata
 * @property string $webhookUrl
 * @property null|string $method
 *
 */
abstract class AbstractCheckout
{
    use Plugins;
    use RequestData;

    /**
     * @var OrderModel
     */
    protected $model;

    /**
     * @var string
     */
    protected $hash;

    /**
     * @var null|MollieAPI
     */
    protected $api;
    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;
    /**
     * @var Bestellung
     */
    protected $oBestellung;

    /**
     * AbstractCheckout constructor.
     * @param Bestellung     $oBestellung
     * @param null|MollieAPI $api
     */
    public function __construct(Bestellung $oBestellung, ?MollieAPI $api = null)
    {
        $this->oBestellung = $oBestellung;
        $this->api         = $api;
    }

    /**
     * @param string $sessionHash
     * @param string $id
     * @param bool   $test
     *
     * @throws ServiceNotFoundException
     * @throws CircularReferenceException
     * @return void
     *
     */
    public static function finalizeOrder(string $sessionHash, string $id, bool $test): void
    {
        $logger = Shop::Container()->getLogService();

        $startTime = microtime(true);
        $debug = PluginHelper::getSetting('debugMode');
        if ($debug) PluginHelper::getLogger()->debug("Mollie - Webhook called for order: " . $sessionHash);

        try {
            if ($paymentSession = PluginHelper::getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash)) {
                if (session_id() !== $paymentSession->cSID) {
                    session_destroy();
                    session_id($paymentSession->cSID);
                    $session = Frontend::getInstance(true, true);
                } else {
                    $session = Frontend::getInstance(false, false);
                }

                if ($debug) PluginHelper::getLogger()->debug('Mollie: payment session when webhook is called: ' . json_encode($paymentSession, JSON_PRETTY_PRINT));

                if (
                    (!isset($_SESSION['Warenkorb']->PositionenArr, $paymentSession->nBezahlt, $paymentSession->kBestellung)
                        || !($paymentSession->nBezahlt && $paymentSession->kBestellung))
                    && count($_SESSION['Warenkorb']->PositionenArr)
                ) {
                    $paymentSession->cNotifyID = $id;
                    $paymentSession->dNotify   = 'NOW()';

                    $api    = new MollieAPI($test);
                    $mollie = strpos($id, 'tr_') === 0 ?
                        $api->getClient()->payments->get($id) :
                        $api->getClient()->orders->get($id, ['embed' => 'payments']);

                    if (in_array($mollie->status, [OrderStatus::STATUS_PENDING, OrderStatus::STATUS_AUTHORIZED, OrderStatus::STATUS_PAID], true)) {
                        if ($debug) PluginHelper::getLogger()->debug('Mollie: order is going to be finalized: ' . $sessionHash);
                        $orderHandler  = new OrderHandler(Shop::Container()->getDB(), Frontend::getCustomer(), Frontend::getCart());
                        $order = $orderHandler->finalizeOrder();
                        $session->cleanUp();
                        $paymentSession->nBezahlt     = 1;
                        $paymentSession->dZeitBezahlt = 'now()';
                    } else if (in_array($mollie->status, [OrderStatus::STATUS_CANCELED, OrderStatus::STATUS_EXPIRED, 'failed'], true)) {
                        if ($debug) PluginHelper::getLogger()->debug("Mollie - Order was canceled by Webhook Call: " . $sessionHash);

                        PluginHelper::getDB()->executeQueryPrepared('UPDATE xplugin_ws5_mollie_orders SET cStatus = :status WHERE cOrderId = :id', [':status' => $mollie->status, ':id' => $mollie->id]);
                        throw new Exception('Mollie Status invalid: ' . $mollie->status . '\n' . print_r([$sessionHash, $id], 1));
                    } else {
                        throw new Exception('Mollie Status invalid: ' . $mollie->status . '\n' . print_r([$sessionHash, $id], 1));
                    }

                    if ($order->kBestellung) {
                        $paymentSession->kBestellung = $order->kBestellung;
                        PluginHelper::getDB()->update('tzahlungsession', 'cZahlungsID', $sessionHash, $paymentSession);
                        if ($debug) {
                            $paymentSessionAfterUpdate = PluginHelper::getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash);
                            PluginHelper::getLogger()->debug('Mollie: payment session updated after order was finalize: ' . json_encode($paymentSessionAfterUpdate, JSON_PRETTY_PRINT));
                        }
                        // End time
                        $endTime = microtime(true);

                        // Calculate execution time in seconds
                        $executionTime = $endTime - $startTime;
                        PluginHelper::getLogger()->debug("Mollie - Order finalized in DB. Execution Time: " . number_format($executionTime, 6) . " seconds");

                        // Handle clicked checkboxes from bestellabschluss
                        if (isset($_SESSION['ws5_mollie_checkboxes'])) {
                            self::handleCheckboxes($order);
                            unset($_SESSION['ws5_mollie_checkboxes']);
                        }


                        try {
                            $checkout = self::fromID($id, false, $order);
                        } catch (Exception $e) {
                            if (strpos($id, 'tr_') === 0) {
                                $checkoutClass = PaymentCheckout::class;
                            } else {
                                $checkoutClass = OrderCheckout::class;
                            }
                            $checkout = new $checkoutClass($order, $api);
                        }

                        if (strpos($mollie->id, 'ord_') === 0) {
                            /** @var Payment $payment */
                            foreach ($mollie->payments() as $payment) {
                                if (in_array($payment->status, [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID, PaymentStatus::STATUS_PENDING])) {
                                    $checkout->getModel()->cTransactionId = $payment->id;
                                    $checkout->getModel()->save();
                                }
                            }
                        }

                        $checkout->updateOrderNumber()
                            ->setExpirationDate()
                            ->handleNotification($sessionHash);

                    } else {
                        if ($debug) PluginHelper::getLogger()->debug('Mollie: no kBestellung after order was finalized: ' . json_encode($order, JSON_PRETTY_PRINT));
                        throw new Exception(sprintf('Bestellung nicht finalisiert: %s', print_r($order, 1)));
                    }
                } else {
                    QueueModel::saveToQueue($_REQUEST['id'], $_REQUEST, 'webhook');

                    throw new Exception(sprintf('PaymentSession bereits bezahlt: %s - ID: %s => Queue', $sessionHash, $id));
                }
            } else {
                QueueModel::saveToQueue($_REQUEST['id'], $_REQUEST, 'webhook');

                throw new Exception(sprintf('PaymentSession nicht gefunden: %s - ID: %s => Queue', $sessionHash, $id));
            }
        } catch (Exception $e) {
            $logger->notice(__NAMESPACE__ . ' finalize order:' . $e->getMessage());
        }
    }

    /**
     * @param string          $id
     * @param bool            $bFill
     * @param null|Bestellung $order
     * @throws RuntimeException
     * @return static
     */
    public static function fromID(string $id, bool $bFill = true, ?Bestellung $order = null): self
    {
        /** @var OrderModel $model */
        $model = OrderModel::fromID($id, 'cOrderId', true);

        $oBestellung = $order;
        if (!$oBestellung) {
            if (!$model->kBestellung) {
                throw new RuntimeException('Keine Bestell-ID hinterlegt.');
            }
            $oBestellung = new Bestellung($model->kBestellung, $bFill);
        }

        if (static::class !== __CLASS__) {
            $self = new static($oBestellung, new MollieAPI($model->bTest));
        } elseif (strpos($model->cOrderId, 'tr_') !== false) {
            $self = new PaymentCheckout($oBestellung, new MollieAPI($model->bTest));
        } else {
            $self = new OrderCheckout($oBestellung, new MollieAPI($model->bTest));
        }
        $self->setModel($model);

        return $self;
    }

    /**
     * Lädt das Model falls vorhanden, oder gibt eun neues leeres zurück
     *
     * @throws Exception
     * @return OrderModel
     */
    public function getModel(): OrderModel
    {
        if (!$this->model) {
            $this->model        = OrderModel::fromID($this->getBestellung()->kBestellung, 'kBestellung');
            $this->model->bTest = $this->getAPI()->isTest();
        }

        return $this->model;
    }

    /**
     * @return static
     */
    protected function setModel(OrderModel $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @throws Exception
     * @return Bestellung
     */
    public function getBestellung(): Bestellung
    {
        if (!$this->oBestellung && $this->getModel()->kBestellung) {
            $this->oBestellung = new Bestellung($this->getModel()->kBestellung, true);
        }

        return $this->oBestellung;
    }

    /**
     * @throws Exception
     * @return MollieAPI
     */
    public function getAPI(): MollieAPI
    {
        if (!$this->api) {
            if ($this->getModel()->cOrderId) {
                $this->api = new MollieAPI($this->getModel()->bTest);
            } else {
                $this->api = new MollieAPI(MollieAPI::getMode());
            }
        }

        return $this->api;
    }

    /**
     * @param null|mixed $hash
     *
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @throws Exception
     * @return void
     *
     */
    public function handleNotification($hash = null): void
    {
        if (!$hash) {
            $hash = $this->getModel()->cHash;
        }

//        try{
//            $pm = $this->getPaymentMethod();
//            if(method_exists($pm, 'generatePUI') && ($pui = $pm->generatePUI($this))){
//                $this->getBestellung()->cPUIZahlungsdaten = $pui;
//                $this->getBestellung()->updateInDB();
//            }
//        }catch (\Exception $e){
//

        $this->updateModel()->saveModel();
        if (null === $this->getBestellung()->dBezahltDatum) {
            if ($incoming = $this->getIncomingPayment()) {
                $this->getPaymentMethod()->addIncomingPayment($this->getBestellung(), $incoming);
                if ($this->completlyPaid()) {
                    $this->getPaymentMethod()->setOrderStatusToPaid($this->getBestellung());
                    $this::makeFetchable($this->getBestellung(), $this->getModel());
                    $this->getPaymentMethod()->deletePaymentHash($hash);

                    $this->Log(sprintf("Checkout::handleNotification: Bestellung '%s' als bezahlt markiert: %.2f %s", $this->getBestellung()->cBestellNr, (float)$incoming->fBetrag, $incoming->cISO));

                    $oZahlungsart = PluginHelper::getDB()->selectSingleRow('tzahlungsart', 'cModulId', $this->getPaymentMethod()->moduleID);
                    if ($oZahlungsart && (int)$oZahlungsart->nMailSenden & ZAHLUNGSART_MAIL_EINGANG) {
                        $this->getPaymentMethod()->sendConfirmationMail($this->getBestellung());
                    }
                } else {
                    $this->Log(sprintf("Checkout::handleNotification: Bestellung '%s': nicht komplett bezahlt: %.2f %s", $this->getBestellung()->cBestellNr, (float)$incoming->fBetrag, $incoming->cISO), LOGLEVEL_ERROR);
                }
            }
        }

    }

    public function setExpirationDate()
    {
        // set expireDate as Bestellattribut
        try {
            if (PluginHelper::getSetting('syncExpirationDate')) {
                if ($order = $this->getMollie()) {
                    if ($this->getBestellung()->kBestellung && isset($order->captureBefore)) {
                        $bestellattribut = new stdClass();
                        $bestellattribut->kBestellung = $this->getBestellung()->kBestellung;
                        $bestellattribut->cName = 'mollieOrderExpirationDate';
                        $bestellattribut->cValue = date("d.m.Y", strtotime($order->captureBefore));
                        PluginHelper::getDB()->insertRow('tbestellattribut', $bestellattribut);
                    }
                }
            }
        } catch (Exception $e) {
            $this->Log('Set Expiration Date nOrderCheckout::setExpirationDate:' . $e->getMessage(), LOGLEVEL_ERROR);
        }

        return $this;
    }

    /**
     * Speichert das Model
     *
     * @throws Exception
     * @return true
     *
     */
    public function saveModel(): bool
    {
        return $this->getModel()->save();
    }

    /**
     * @throws Exception
     * @return static
     */
    public function updateModel(): self
    {
        if ($this->getMollie()) {
            $this->getModel()->cOrderId  = $this->getMollie()->id;
            $this->getModel()->cLocale   = $this->getMollie()->locale;
            $this->getModel()->fAmount   = $this->getMollie()->amount->value;
            $this->getModel()->cMethod   = $this->getMollie()->method;
            $this->getModel()->cCurrency = $this->getMollie()->amount->currency;
            $this->getModel()->cStatus   = $this->getMollie()->status;
        }

        // TODO: DOKU, Reminder Email, name der paymentmethod in array
        if (!defined('MOLLIE_DISABLE_REMINDER')) {
            define('MOLLIE_DISABLE_REMINDER', []);
        }
        if (is_array(MOLLIE_DISABLE_REMINDER) && $this->getModel()->cMethod && in_array($this->getModel()->cMethod, MOLLIE_DISABLE_REMINDER)) {
            $this->getModel()->dReminder = date('Y-m-d H:i:s');
        }

        $this->getModel()->kBestellung = $this->getBestellung()->kBestellung ?: ModelInterface::NULL;
        $this->getModel()->cBestellNr = $this->getBestellung()->cBestellNr;
        $this->getModel()->bSynced = $this->getModel()->bSynced ?? !PluginHelper::getSetting('onlyPaid');

        return $this;
    }

    abstract public function getMollie(bool $force = false);

    /**
     * @return stdClass
     */
    abstract public function getIncomingPayment(): ?stdClass;

    /**
     * @throws Exception
     * @return FallbackMethod|MethodInterface|PaymentMethod|\Plugin\ws5_mollie\lib\PaymentMethod
     */
    public function getPaymentMethod()
    {
        if (!$this->paymentMethod) {
            if ($this->getBestellung()->Zahlungsart && strpos($this->getBestellung()->Zahlungsart->cModulId, "kPlugin_{$this::Plugin('ws5_mollie')->getID()}_") !== false) {
                $this->paymentMethod = LegacyMethod::create($this->getBestellung()->Zahlungsart->cModulId);
            } else {
                $this->paymentMethod = LegacyMethod::create("kPlugin_{$this::Plugin('ws5_mollie')->getID()}_mollie");
            }
        }

        return $this->paymentMethod;
    }

    /**
     * @throws Exception
     * @return bool
     */
    public function completlyPaid(): bool
    {
        if (
            $row = PluginHelper::getDB()->executeQueryPrepared('SELECT SUM(fBetrag) as fBetragSumme FROM tzahlungseingang WHERE kBestellung = :kBestellung', [
                ':kBestellung' => $this->oBestellung->kBestellung
            ], 1)
        ) {
            // Berechne Bestellwert anhand des Waehrungsfaktors und runde den Wert ab um Fehler aufgrund der Nachkommastellen zu verhindern
            $sum = floor(($this->oBestellung->fGesamtsumme * $this->getBestellung()->fWaehrungsFaktor) * 100) / 100;
            return (float)$row->fBetragSumme >= $sum;
        }

        return false;
    }

    /**
     * @param Bestellung $oBestellung
     * @param OrderModel $model
     * @throws ServiceNotFoundException
     * @throws CircularReferenceException
     * @return bool
     */
    public static function makeFetchable(Bestellung $oBestellung, OrderModel $model): bool
    {
        if ($oBestellung->cAbgeholt === 'Y' && !$model->bSynced) {
            PluginHelper::getDB()->update('tbestellung', 'kBestellung', $oBestellung->kBestellung, (object)['cAbgeholt' => 'N']);
            $model->bSynced = true;

            try {
                return $model->save();
            } catch (Exception $e) {
                Shop::Container()->getLogService()->error(sprintf('Fehler beim speichern des Models: %s / Bestellung: %s', $model->kId, $oBestellung->cBestellNr));
            }
        }

        return false;
    }

    /**
     * @param $msg
     * @param int $level
     * @throws ServiceNotFoundException
     * @throws CircularReferenceException
     * @return $this
     */
    public function Log(string $msg, $level = LOGLEVEL_NOTICE)
    {
        try {
            $data = '';
            if ($this->getBestellung()) {
                $data .= '#' . $this->getBestellung()->kBestellung;
            }
            if ($this->getMollie()) {
                $data .= '$' . $this->getMollie()->id;
            }
            ZahlungsLog::add($this->getPaymentMethod()->moduleID, '[' . microtime(true) . ' - ' . $_SERVER['PHP_SELF'] . '] ' . $msg, $data, $level);
        } catch (Exception $e) {
            Shop::Container()->getLogService()->error(sprintf("Error while Logging: %s\nPrevious Error: %s", $e->getMessage(), $msg));
        }

        return $this;
    }

    /**
     * @return $this
     */
    abstract protected function updateOrderNumber();

    /**
     * @param int  $kBestellung
     * @param bool $checkZA
     * @return bool
     */
    public static function isMollie(int $kBestellung, bool $checkZA = false): bool
    {
        if ($checkZA) {
            $res = PluginHelper::getDB()->executeQueryPrepared('SELECT * FROM tzahlungsart WHERE cModulId LIKE :cModulId AND kZahlungsart = :kZahlungsart', [
                ':kZahlungsart' => $kBestellung,
                ':cModulId' => 'kPlugin_' . PluginHelper::getPlugin()->getID() . '\_%'
            ], 1);

            return (bool)$res;
        }

        return ($res = PluginHelper::getDB()->executeQueryPrepared('SELECT kId FROM xplugin_ws5_mollie_orders WHERE kBestellung = :kBestellung;', [
                ':kBestellung' => $kBestellung,
            ], 1)) && $res->kId;
    }

    /**
     * @param Bestellung     $oBestellung
     * @param null|MollieAPI $api
     * @return static
     */
    public static function factory(Bestellung $oBestellung, ?MollieAPI $api = null): self
    {
        return new static($oBestellung, $api);
    }

    /**
     * @param int   $kBestellung
     * @param mixed $fill
     * @return OrderCheckout|PaymentCheckout
     */
    public static function fromBestellung(int $kBestellung, $fill = true)
    {
        $model = OrderModel::fromID($kBestellung, 'kBestellung', true);

        if (!$model->kBestellung) {
            throw new RuntimeException(sprintf("Bestellung '%d' konnte nicht geladen werden.", $kBestellung));
        }
        $oBestellung = new Bestellung($model->kBestellung, $fill);
        if (!$oBestellung->kBestellung) {
            throw new RuntimeException(sprintf("Bestellung '%d' konnte nicht geladen werden.", $kBestellung));
        }
        if (strpos($model->cOrderId, 'tr_') !== false) {
            $self = new PaymentCheckout($oBestellung, new MollieAPI($model->bTest));
        } else {
            $self = new OrderCheckout($oBestellung, new MollieAPI($model->bTest));
        }
        $self->setModel($model);

        return $self;
    }

    /**
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function sendReminders(): void
    {
        $reminder = (int)PluginHelper::getSetting('reminder');

        if (!$reminder) {
            PluginHelper::getDB()->executeQueryPrepared('UPDATE xplugin_ws5_mollie_orders SET dReminder = :dReminder WHERE dReminder IS NULL', [
                ':dReminder' => date('Y-m-d H:i:s')
            ], 3);

            return;
        }
        // TODO: DOKU
        ifndef('MOLLIE_REMINDER_LIMIT_DAYS', 7);
        $remindables = PluginHelper::getDB()->executeQueryPrepared("SELECT kId FROM xplugin_ws5_mollie_orders mo LEFT JOIN tbestellung tb ON mo.kBestellung = tb.kBestellung WHERE tb.cStatus != -1 AND (mo.dReminder IS NULL OR mo.dReminder = '0000-00-00 00:00:00') AND mo.dCreated > NOW() - INTERVAL " . MOLLIE_REMINDER_LIMIT_DAYS . " DAY AND mo.dCreated < NOW() - INTERVAL :d MINUTE AND mo.cStatus IN ('created','open', 'expired', 'failed', 'canceled')", [
            ':d' => $reminder
        ], 2);
        foreach ($remindables as $remindable) {
            try {
                self::sendReminder($remindable->kId);
            } catch (Exception $e) {
                Shop::Container()->getBackendLogService()->error('AbstractCheckout::sendReminders: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param $kID
     * @param mixed $kId
     *
     * @throws Exception
     * @return true
     *
     */
    public static function sendReminder($kId): bool
    {
        $order = OrderModel::fromID($kId, 'kId', true);

        // filter paid and storno
        if (!$order->kBestellung || (int)$order->cStatus > BESTELLUNG_STATUS_IN_BEARBEITUNG || (int)$order->cStatus < 0) {
            $order->dReminder = date('Y-m-d H:i:s');
            $order->save();

            return true;
        }
        $oBestellung = new Bestellung($order->kBestellung);
        $repayURL    = Shop::getURL() . '/?m_pay=' . md5($order->kId . '-' . $order->kBestellung);

        $data = new stdClass();
        $data->tkunde = new Customer($oBestellung->kKunde);
        if (!$data->tkunde->kKunde) {
            $order->dReminder = date('Y-m-d H:i:s');
            $order->save();

            throw new Exception("Kunde '$oBestellung->kKunde' nicht gefunden.");
        }
        $data->Bestellung = $oBestellung;
        $data->PayURL = $repayURL;
        $data->Amount = Preise::getLocalizedPriceString($order->fAmount, Currency::fromISO($order->cCurrency), false);

        $mailer = Shop::Container()->get(Mailer::class);
        $mail = new Mail();
        $mail->createFromTemplateID('kPlugin_' . PluginHelper::getPlugin()->getID() . '_zahlungserinnerung', $data);

        $order->dReminder = date('Y-m-d H:i:s');
        $order->save();

        if (!$mailer->send($mail)) {
            throw new Exception($mail->getError() . "\n" . print_r([$data, $order->jsonSerialize()], 1));
        }

        return true;
    }

    /**
     * cancels oder refunds eine stornierte Bestellung
     *
     * @return string
     */
    abstract public function cancelOrRefund(): string;

    /**
     * @param array $options
     * @throws Exception
     * @return self
     */
    public function loadRequest(array &$options = [])
    {
        if ($this->getBestellung()) {
            $oKunde = !$this->getBestellung()->oKunde && $this->getPaymentMethod()->duringCheckout
                ? $_SESSION['Kunde']
                : $this->getBestellung()->oKunde;

            $this->amount = new Amount($this->getBestellung()
                    ->fGesamtsumme * $this->getBestellung()->fWaehrungsFaktor, $this->getBestellung()->Waehrung, true);
            $this->metadata = [
                'kBestellung'   => $this->getBestellung()->kBestellung,
                'kKunde'        => $oKunde->kKunde,
                'kKundengruppe' => $oKunde->kKundengruppe,
                'cHash'         => $this->getHash(),
            ];

            if (
                defined(get_class($this->getPaymentMethod()) . '::METHOD') && $this->getPaymentMethod()::METHOD !== ''
                && (!PluginHelper::getSetting('resetMethod') || !$this->getMollie())
            ) {
                $this->method = $this->getPaymentMethod()::METHOD;
            }

            $this->redirectUrl = $this->getPaymentMethod()->duringCheckout ?
            Shop::Container()->getLinkService()->getStaticRoute('bestellabschluss.php') . "?" . http_build_query(['hash' => $this->getHash()]) :
                $this->getPaymentMethod()->getReturnURL($this->getBestellung());

            $this->webhookUrl = $this->getWebhookUrl();
        }

        $this->locale = Locale::getLocale(Frontend::get('cISOSprache', 'ger'), Frontend::getCustomer()->cLand);

        return $this;
    }

    /**
     * @throws Exception
     * @return string
     */
    public function getHash(): string
    {
        if ($this->getModel()->cHash) {
            return $this->getModel()->cHash;
        }
        if (!$this->hash) {
            $this->hash = $this->getPaymentMethod()->generateHash($this->getBestellung());
        }

        return $this->hash;
    }

    /**
     * @throws Exception
     * @return string
     */
    protected function getWebhookUrl(): string
    {
        $query = [
            'mollie' => 1,
        ];
        if ($this->getPaymentMethod()->duringCheckout) {
            $query['hash'] = $this->getHash();
            $query['test'] = $this->getAPI()->isTest() ?: null;
        }

        return Shop::getURL(true) . '/?' . http_build_query($query);
    }

    /**
     * @param array $paymentOptions
     * @return Order|Payment
     */
    abstract public function create(array $paymentOptions = []);

    /**
     * @throws Exception
     */
    public function storno(): void
    {
        if (in_array((int)$this->getBestellung()->cStatus, [BESTELLUNG_STATUS_OFFEN, BESTELLUNG_STATUS_IN_BEARBEITUNG], true)) {

            $log                   = [];
            $conf                  = Shop::getSettings([CONF_GLOBAL]);
            $nArtikelAnzeigefilter = (int)$conf['global']['artikel_artikelanzeigefilter'];
            foreach ($this->getBestellung()->Positionen as $pos) {
                if ($pos->kArtikel && $pos->Artikel && $pos->Artikel->cLagerBeachten === 'Y') {
                    $log[] = sprintf('Reset stock of "%s" by %d', $pos->Artikel->cArtNr, -1 * $pos->nAnzahl);
                    self::aktualisiereLagerbestand($pos->Artikel, -1 * $pos->nAnzahl, $pos->WarenkorbPosEigenschaftArr, $nArtikelAnzeigefilter);
                }
            }
            $log[] = sprintf("Cancel order '%s'.", $this->getBestellung()->cBestellNr);

            if (PluginHelper::getDB()->executeQueryPrepared('UPDATE tbestellung SET cStatus = :cStatus WHERE kBestellung = :kBestellung', [':cStatus' => '-1', ':kBestellung' => $this->getBestellung()->kBestellung], 3)) {
                $this->Log(implode('\n', $log));
            }
        }
    }

    protected static function aktualisiereLagerbestand(Artikel $product, int $amount, array $attributeValues, int $productFilter = 1)
    {
        $inventory = $product->fLagerbestand;
        $db = PluginHelper::getDB();
        $stockUpdater = new StockUpdater(Shop::Container()->getDB(), Frontend::getCustomer(), Frontend::getCart());
        if ($product->cLagerBeachten !== 'Y') {
            return $inventory;
        }
        if (
            $product->cLagerVariation === 'Y'
            && count($attributeValues) > 0
        ) {
            foreach ($attributeValues as $value) {
                $EigenschaftWert = new EigenschaftWert($value->kEigenschaftWert);
                if ($EigenschaftWert->fPackeinheit == 0) {
                    $EigenschaftWert->fPackeinheit = 1;
                }
                $db->queryPrepared(
                    'UPDATE teigenschaftwert
                    SET fLagerbestand = fLagerbestand - :inv
                    WHERE kEigenschaftWert = :aid',
                    [
                        'aid' => (int)$value->kEigenschaftWert,
                        'inv' => $amount * $EigenschaftWert->fPackeinheit
                    ],
                    ReturnType::DEFAULT
                );
            }
            $stockUpdater->updateProductStockLevel($product->kArtikel, $amount, $product->fPackeinheit);
        } elseif ($product->fPackeinheit > 0) {
            if ($product->kStueckliste > 0) {
                $inventory = $stockUpdater->updateBOMStockLevel($product, $amount);
            } else {
                $stockUpdater->updateProductStockLevel($product->kArtikel, $amount, $product->fPackeinheit);
                $tmpProduct = $db->select(
                    'tartikel',
                    'kArtikel',
                    $product->kArtikel,
                    null,
                    null,
                    null,
                    null,
                    false,
                    'fLagerbestand'
                );
                if ($tmpProduct !== null) {
                    $inventory = (float)$tmpProduct->fLagerbestand;
                }
                // Stücklisten Komponente
                if (Product::isStuecklisteKomponente($product->kArtikel)) {
                    $stockUpdater->updateBOMStock(
                        $product->kArtikel,
                        $inventory,
                        $product->cLagerKleinerNull === 'Y'
                    );
                }
            }
            // Aktualisiere Merkmale in tartikelmerkmal vom Vaterartikel
            if ($product->kVaterArtikel > 0) {
                Artikel::beachteVarikombiMerkmalLagerbestand($product->kVaterArtikel, $productFilter);
                $stockUpdater->updateProductStockLevel($product->kVaterArtikel, $amount, $product->fPackeinheit);
            }
        }

        return $inventory;
    }


    private static function handleCheckboxes(Bestellung $order): void
    {
        // Trigger checkbox function nachträglich, wenn diese im bestellabschluss geklickt wurden
        /**
         * @var \JTL\Customer\Customer $customer
         */
        $customer = $_SESSION['Kunde'] ?? $order->oKunde;
        if (!($customer instanceof Customer)) {
            $customer = new Customer();
        }
        $customerGroupID   = $customer->getGroupID();
        $checkbox          = new CheckBox(0, PluginHelper::getDB());
        $checkbox->triggerSpecialFunction(
            \CHECKBOX_ORT_BESTELLABSCHLUSS,
            $customerGroupID,
            true,
            $_SESSION['ws5_mollie_checkboxes'],
            ['oBestellung' => $order, 'oKunde' => $customer]
        );

        // Logge checkboxen nachträglich
        $checkboxes = $checkbox->getCheckBoxFrontend(\CHECKBOX_ORT_BESTELLABSCHLUSS, $customerGroupID, true, false, false, true);
        foreach ($checkboxes as $checkbox) {
            $checked          = self::checkboxWasChecked($checkbox->cID, $_SESSION['ws5_mollie_checkboxes']);
            if ($checked) {
                PluginHelper::getDB()->executeQueryPrepared('UPDATE tcheckboxlogging SET bChecked = 1 WHERE kCheckbox = :kCheckbox AND kBestellung = :kBestellung',
                    [
                        'kCheckbox' => $checkbox->kCheckBox,
                        'kBestellung' => $order->kBestellung
                    ], 10);
            }
        }
    }

    private static function checkboxWasChecked(string $idx, array $post): bool
    {
        $value = $post[$idx] ?? null;
        if ($value === null) {
            return false;
        }
        if ($value === 'on' || $value === 'Y' || $value === 'y') {
            $value = true;
        } elseif ($value === 'N' || $value === 'n' || $value === '') {
            $value = false;
        } else {
            $value = (bool)$value;
        }

        return $value;
    }

    /**
     * @throws Exception
     * @return string
     *
     */
    public function getDescription(): string
    {
        $descTemplate = trim(PluginHelper::getSetting('paymentDescTpl')) ?: 'Order {orderNumber}';
        $oKunde = $this->getBestellung()->oKunde ?: $_SESSION['Kunde'];

        return str_replace([
            '{orderNumber}',
            '{storeName}',
            '{customer.firstname}',
            '{customer.lastname}',
            '{customer.company}',
        ], [
            $this->getBestellung()->cBestellNr,
            Shopsetting::getInstance()->getValue(CONF_GLOBAL, 'global_shopname'),  //Shop::getSettings([CONF_GLOBAL])['global']['global_shopname'],
            $oKunde->cVorname,
            $oKunde->cNachname,
            $oKunde->cFirma
        ], $descTemplate);
    }

    /**
     * @param Order|Payment $model
     * @return $this;
     */
    abstract protected function setMollie($model);
}
