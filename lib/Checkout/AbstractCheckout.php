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
use JTL\Checkout\Bestellung;
use JTL\Checkout\ZahlungsLog;
use JTL\Customer\Customer;
use JTL\DB\ReturnType;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
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
use PaymentMethod;
use Plugin\ws5_mollie\lib\Locale;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Order\Amount;
use Plugin\ws5_mollie\lib\Traits\RequestData;
use stdClass;
use WS\JTL5\Model\AbstractModel;

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
    use \WS\JTL5\Traits\Plugin;
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
    public function __construct(Bestellung $oBestellung, MollieAPI $api = null)
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

        try {
            if ($paymentSession = Shop::Container()->getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash)) {
                if (session_id() !== $paymentSession->cSID) {
                    session_destroy();
                    session_id($paymentSession->cSID);
                    $session = Frontend::getInstance(true, true);
                } else {
                    $session = Frontend::getInstance(false, false);
                }

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

                        require_once PFAD_ROOT . PFAD_INCLUDES . 'bestellabschluss_inc.php';
                        require_once PFAD_ROOT . PFAD_INCLUDES . 'mailTools.php';

                        $order = finalisiereBestellung();
                        $session->cleanUp();
                        $paymentSession->nBezahlt     = 1;
                        $paymentSession->dZeitBezahlt = 'now()';
                    } else {
                        throw new Exception('Mollie Status invalid: ' . $mollie->status . '\n' . print_r([$sessionHash, $id], 1));
                    }

                    if ($order->kBestellung) {
                        $paymentSession->kBestellung = $order->kBestellung;
                        Shop::Container()->getDB()->update('tzahlungsession', 'cZahlungsID', $sessionHash, $paymentSession);

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
                            ->handleNotification($sessionHash);
                    } else {
                        throw new Exception(sprintf('Bestellung nicht finalisiert: %s', print_r($order, 1)));
                    }
                } else {
                    throw new Exception(sprintf('PaymentSession bereits bezahlt: %s - ID: %s => Queue', $sessionHash, $id));
                }
            } else {
                throw new Exception(sprintf('PaymentSession nicht gefunden: %s - ID: %s => Queue', $sessionHash, $id));
            }
        } catch (Exception $e) {
            $logger->error($e->getMessage());
        }
    }

    /**
     * @param string          $id
     * @param bool            $bFill
     * @param null|Bestellung $order
     *
     * @return static
     */
    public static function fromID(string $id, bool $bFill = true, Bestellung $order = null): self
    {
        /** @var OrderModel $model */
        $model = OrderModel::fromID($id, 'cOrderId', true);

        $oBestellung = $order;
        if (!$oBestellung) {
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
     * @throws ServiceNotFoundException
     * @throws Exception
     * @throws CircularReferenceException
     * @return void
     *
     */
    public function handleNotification($hash = null): void
    {
        if (!$hash) {
            $hash = $this->getModel()->cHash;
        }

        $this->updateModel()->saveModel();
        if (null === $this->getBestellung()->dBezahltDatum) {
            if ($incoming = $this->getIncomingPayment()) {
                $this->getPaymentMethod()->addIncomingPayment($this->getBestellung(), $incoming);
                if ($this->completlyPaid()) {
                    $this->getPaymentMethod()->setOrderStatusToPaid($this->getBestellung());
                    $this::makeFetchable($this->getBestellung(), $this->getModel());
                    $this->getPaymentMethod()->deletePaymentHash($hash);

                    $this->Log(sprintf("Checkout::handleNotification: Bestellung '%s' als bezahlt markiert: %.2f %s", $this->getBestellung()->cBestellNr, (float)$incoming->fBetrag, $incoming->cISO));

                    $oZahlungsart = Shop::Container()->getDB()->selectSingleRow('tzahlungsart', 'cModulId', $this->getPaymentMethod()->moduleID);
                    if ($oZahlungsart && (int)$oZahlungsart->nMailSenden === 1) {
                        $this->getPaymentMethod()->sendConfirmationMail($this->getBestellung());
                    }
                } else {
                    $this->Log(sprintf("Checkout::handleNotification: Bestellung '%s': nicht komplett bezahlt: %.2f %s", $this->getBestellung()->cBestellNr, (float)$incoming->fBetrag, $incoming->cISO), LOGLEVEL_ERROR);
                }
            }
        }
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
        $this->getModel()->kBestellung = $this->getBestellung()->kBestellung ?: AbstractModel::NULL;
        $this->getModel()->cBestellNr  = $this->getBestellung()->cBestellNr;
        $this->getModel()->bSynced     = $this->getModel()->bSynced ?? (self::Plugin('ws5_mollie')->getConfig()->getValue('onlyPaid') !== 'on');

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
     * @return bool
     */
    public function completlyPaid(): bool
    {
        if (
            $row = Shop::Container()->getDB()->executeQueryPrepared('SELECT SUM(fBetrag) as fBetragSumme FROM tzahlungseingang WHERE kBestellung = :kBestellung', [
            ':kBestellung' => $this->oBestellung->kBestellung
            ], 1)
        ) {
            return (float)$row->fBetragSumme >= ($this->oBestellung->fGesamtsumme * $this->getBestellung()->fWaehrungsFaktor);
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
            Shop::Container()->getDB()->update('tbestellung', 'kBestellung', (int)$oBestellung->kBestellung, (object)['cAbgeholt' => 'N']);
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
     * @param int  $kBestellung
     * @param bool $checkZA
     * @return bool
     */
    public static function isMollie(int $kBestellung, bool $checkZA = false): bool
    {
        if ($checkZA) {
            $res = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM tzahlungsart WHERE cModulId LIKE :cModulId AND kZahlungsart = :kZahlungsart', [
                ':kZahlungsart' => $kBestellung,
                ':cModulId'     => 'kPlugin_' . self::Plugin('ws5_mollie')->getID() . '%'
            ], 1);

            return $res ? true : false;
        }

        return ($res = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId FROM xplugin_ws5_mollie_orders WHERE kBestellung = :kBestellung;', [
                ':kBestellung' => $kBestellung,
            ], 1)) && $res->kId;
    }

    /**
     * @param Bestellung     $oBestellung
     * @param null|MollieAPI $api
     * @return static
     */
    public static function factory(Bestellung $oBestellung, MollieAPI $api = null): self
    {
        return new static($oBestellung, $api);
    }

    /**
     * @param mixed $fill
     * @return OrderCheckout|PaymentCheckout
     */
    public static function fromBestellung(int $kBestellung, $fill = true)
    {
        $model = OrderModel::fromID($kBestellung, 'kBestellung', true);

        $oBestellung = new Bestellung($model->kBestellung, $fill);
        if (!$oBestellung->kBestellung) {
            throw new Exception(sprintf("Bestellung '%d' konnte nicht geladen werden.", $kBestellung));
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
        $reminder = (int)self::Plugin('ws5_mollie')->getConfig()->getValue('reminder');

        if (!$reminder) {
            Shop::Container()->getDB()->executeQueryPrepared('UPDATE xplugin_ws5_mollie_orders SET dReminder = :dReminder WHERE dReminder IS NULL', [
                ':dReminder' => date('Y-m-d H:i:s')
            ], 3);

            return;
        }

        $remindables = Shop::Container()->getDB()->executeQueryPrepared("SELECT kId FROM xplugin_ws5_mollie_orders WHERE dReminder IS NULL AND dCreated < NOW() - INTERVAL :d HOUR AND cStatus IN ('created','open', 'expired', 'failed', 'canceled')", [
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
            return true;
        }
        $oBestellung = new Bestellung($order->kBestellung);
        $repayURL    = Shop::getURL() . '/?m_pay=' . md5($order->kId . '-' . $order->kBestellung);

        $data         = new stdClass();
        $data->tkunde = new Customer($oBestellung->kKunde);
        if (!$data->tkunde->kKunde) {
            $order->dReminder = date('Y-m-d H:i:s');
            $order->save();

            throw new Exception("Kunde '{$oBestellung->kKunde}' nicht gefunden.");
        }
        $data->Bestellung = $oBestellung;
        $data->PayURL     = $repayURL;
        $data->Amount     = Preise::getLocalizedPriceString($order->fAmount, Currency::fromISO($order->cCurrency), false);

        $mailer = Shop::Container()->get(Mailer::class);
        $mail   = new Mail();
        $mail->createFromTemplateID('kPlugin_' . self::Plugin('ws5_mollie')->getID() . '_zahlungserinnerung', $data);

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
                && (self::Plugin('ws5_mollie')->getConfig()->getValue('resetMethod') !== 'on' || !$this->getMollie())
            ) {
                $this->method = $this->getPaymentMethod()::METHOD;
            }

            $this->redirectUrl = $this->getPaymentMethod()->duringCheckout ?
                Shop::getURL() . '/bestellabschluss.php?' . http_build_query(['hash' => $this->getHash()]) :
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
            require_once PFAD_ROOT . PFAD_INCLUDES . 'bestellabschluss_inc.php';

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

            if (Shop::Container()->getDB()->executeQueryPrepared('UPDATE tbestellung SET cAbgeholt = "N", cStatus = :cStatus WHERE kBestellung = :kBestellung', [':cStatus' => '-1', ':kBestellung' => $this->getBestellung()->kBestellung], 3)) {
                $this->Log(implode('\n', $log));
            }
        }
    }

    protected static function aktualisiereLagerbestand(Artikel $product, int $amount, array $attributeValues, int $productFilter = 1)
    {
        $inventory = (float)$product->fLagerbestand;
        $db        = Shop::Container()->getDB();
        if ($product->cLagerBeachten !== 'Y') {
            return $inventory;
        }
        if (
            $product->cLagerVariation === 'Y'
            && is_array($attributeValues)
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
            updateStock($product->kArtikel, $amount, $product->fPackeinheit);
        } elseif ($product->fPackeinheit > 0) {
            if ($product->kStueckliste > 0) {
                $inventory = aktualisiereStuecklistenLagerbestand($product, $amount);
            } else {
                updateStock($product->kArtikel, $amount, $product->fPackeinheit);
                $tmpProduct = $db->select(
                    'tartikel',
                    'kArtikel',
                    (int)$product->kArtikel,
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
                    aktualisiereKomponenteLagerbestand(
                        $product->kArtikel,
                        $inventory,
                        $product->cLagerKleinerNull === 'Y'
                    );
                }
            }
            // Aktualisiere Merkmale in tartikelmerkmal vom Vaterartikel
            if ($product->kVaterArtikel > 0) {
                Artikel::beachteVarikombiMerkmalLagerbestand($product->kVaterArtikel, $productFilter);
                updateStock($product->kVaterArtikel, $amount, $product->fPackeinheit);
            }
        }

        return $inventory;
    }

    /**
     * @throws Exception
     * @return string
     *
     */
    public function getDescription(): string
    {
        $descTemplate = trim(self::Plugin('ws5_mollie')->getConfig()->getValue('paymentDescTpl')) ?: 'Order {orderNumber}';
        $oKunde       = $this->getBestellung()->oKunde ?: $_SESSION['Kunde'];

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
     * @return $this
     */
    abstract protected function updateOrderNumber();

    /**
     * @param Order|Payment $model
     * @return $this;
     */
    abstract protected function setMollie($model);
}
