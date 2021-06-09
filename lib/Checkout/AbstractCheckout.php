<?php


namespace Plugin\ws5_mollie\lib\Checkout;


use Exception;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Bestellung;
use JTL\Checkout\ZahlungsLog;
use JTL\Customer\Customer;
use JTL\Mail\Mail\Mail;
use JTL\Mail\Mailer;
use JTL\Model\DataModel;
use JTL\Plugin\Payment\LegacyMethod;
use JTL\Shop;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use PaymentMethod;
use Plugin\ws5_mollie\lib\Model\OrderModel;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Traits\Plugin;
use Plugin\ws5_mollie\lib\Traits\RequestData;
use SmartyException;
use stdClass;

abstract class AbstractCheckout
{
    use Plugin;

    use RequestData;

    /**
     * @var OrderModel
     */
    protected $model;

    protected $reqData;

    protected $hash;

    /**
     * @var MollieAPI|null
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
     * @param Bestellung $oBestellung
     * @param MollieAPI|null $api
     */
    public function __construct(Bestellung $oBestellung, MollieAPI $api = null)
    {
        $this->oBestellung = $oBestellung;
        $this->api = $api;
    }

    /**
     * @param string $hash
     * @param string $id
     * @param bool $test
     */
    public static function finalizeOrder(string $hash, string $id, bool $test)
    {
// TODO
    }

    /**
     * @param int $kBestellung
     * @param bool $checkZA
     * @return bool
     */
    public static function isMollie(int $kBestellung, bool $checkZA = false): bool
    {
        if ($checkZA) {
            $res = Shop::Container()->getDB()->executeQueryPrepared('SELECT * FROM tzahlungsart WHERE cModulId LIKE :cModulId AND kZahlungsart = :kZahlungsart', [
                ':kZahlungsart' => $kBestellung,
                ':cModulId' => 'kPlugin_' . self::Plugin()->getID() . '%'
            ], 1);
            return $res ? true : false;
        }

        return ($res = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId FROM xplugin_ws5_mollie_orders WHERE kBestellung = :kBestellung;', [
                ':kBestellung' => $kBestellung,
            ], 1)) && $res->kId;
    }

    /**
     * @param Bestellung $oBestellung
     * @param MollieAPI|null $api
     * @return static
     */
    public static function factory(Bestellung $oBestellung, MollieAPI $api = null): AbstractCheckout
    {
        return new static($oBestellung, $api);
    }

    /**
     * @param $id
     * @param MollieAPI|null $api
     * @return AbstractCheckout
     * @throws Exception
     */
    public static function fromID($id)
    {
        $model = OrderModel::loadByAttributes([
            'orderId' => $id,
        ], Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_FAIL);
        $oBestellung = new Bestellung($model->getBestellung(), true);

        if (static::class !== AbstractCheckout::class) {
            $self = new static($oBestellung, new MollieAPI($model->getTest()));
        } else {
            if (strpos($model->getOrderId(), 'tr_') !== false) {
                $self = new PaymentCheckout($oBestellung, new MollieAPI($model->getTest()));
            } else {
                $self = new OrderCheckout($oBestellung, new MollieAPI($model->getTest()));
            }
        }
        $self->setModel($model);
        return $self;
    }

    public static function fromBestellung($kBestellung): AbstractCheckout
    {
        $model = OrderModel::loadByAttributes([
            'bestellung' => $kBestellung,
        ], Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_FAIL);
        $oBestellung = new Bestellung($model->getBestellung(), true);
        if (!$oBestellung->kBestellung) {
            throw new Exception(sprintf("Bestellung '%d' konnte nicht geladen werden.", $kBestellung));
        }
        if (strpos($model->getOrderId(), 'tr_') !== false) {
            $self = new PaymentCheckout($oBestellung, new MollieAPI($model->getTest()));
        } else {
            $self = new OrderCheckout($oBestellung, new MollieAPI($model->getTest()));
        }
        $self->setModel($model);
        return $self;
    }

    /**
     * @throws SmartyException
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public static function sendReminders(): void
    {
        $reminder = (int)self::Plugin()->getConfig()->getValue('reminder');

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
                Shop::Container()->getBackendLogService()->error("AbstractCheckout::sendReminders: " . $e->getMessage());
            }
        }
    }

    /**
     * @param $kID
     * @return bool
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws SmartyException
     */
    public static function sendReminder($kID): bool
    {

        $order = OrderModel::loadByAttributes(['id' => $kID], Shop::Container()->getDB(), OrderModel::ON_NOTEXISTS_FAIL);

        $oBestellung = new Bestellung($order->getBestellung());
        $repayURL = Shop::getURL() . '/?m_pay=' . md5($order->getId() . '-' . $order->getBestellung());

        $data = new stdClass();
        $data->tkunde = new Customer($oBestellung->kKunde);
        if (!$data->tkunde->kKunde) {
            $order->setReminder(date('Y-m-d H:i:s'));
            $order->save(['reminder']);
            throw new Exception("Kunde '{$oBestellung->kKunde}' nicht gefunden.");
        }
        $data->Bestellung = $oBestellung;
        $data->PayURL = $repayURL;
        $data->Amount = Preise::getLocalizedPriceString($order->getAmount(), Currency::fromISO($order->getCurrency()), false);

        $mailer = Shop::Container()->get(Mailer::class);
        $mail = new Mail();
        $mail->createFromTemplateID('kPlugin_' . self::Plugin()->getID() . '_zahlungserinnerung', $data);

        $order->setReminder(date('Y-m-d H:i:s'));
        $order->save(['reminder']);

        if (!$mailer->send($mail)) {
            throw new Exception($mail->getError() . "\n" . print_r([$data, $order->rawArray()], 1));
        }
        return true;
    }

    public function Log($msg, $level = LOGLEVEL_NOTICE)
    {
        try {
            $data = '';
            if ($this->getBestellung()) {
                $data .= '#' . $this->getBestellung()->kBestellung;
            }
            if ($this->getMollie()) {
                $data .= '$' . $this->getMollie()->id;
            }
            ZahlungsLog::add($this->getPaymentMethod()->moduleID, "[" . microtime(true) . " - " . $_SERVER['PHP_SELF'] . "] " . $msg, $data, $level);
        } catch (Exception $e) {
            Shop::Container()->getLogService()->error(sprintf("Error while Logging: %s\nPrevious Error: %s", $e->getMessage(), $msg));
        }
        return $this;
    }

    /**
     * @return Bestellung
     * @throws Exception
     */
    public function getBestellung(): ?Bestellung
    {
        if (!$this->oBestellung && $this->getModel()->getBestellung()) {
            $this->oBestellung = new Bestellung($this->getModel()->getBestellung(), true);
        }
        return $this->oBestellung;
    }

    /**
     * Lädt das Model falls vorhanden, oder gibt eun neues leeres zurück
     *
     * @return OrderModel
     * @throws Exception
     */
    public function getModel(): OrderModel
    {
        if (!$this->model) {
            $this->model = OrderModel::loadByAttributes([
                'bestellung' => $this->oBestellung->kBestellung,
            ], Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_NEW);
            $this->model->setTest($this->getAPI()->isTest());
        }
        return $this->model;
    }

    protected function setModel(OrderModel $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * @return MollieAPI
     */
    public function getAPI(): MollieAPI
    {
        if (!$this->api) {
            if ($this->getModel()->getOrderId()) {
                $this->api = new MollieAPI($this->getModel()->getTest());
            } else {
                $this->api = new MollieAPI(MollieAPI::getMode());
            }
        }
        return $this->api;
    }

    abstract public function getMollie($force = false);

    /**
     * @return \JTL\Plugin\Payment\FallbackMethod|\JTL\Plugin\Payment\MethodInterface|PaymentMethod|\Plugin\ws5_mollie\lib\PaymentMethod
     * @throws Exception
     */
    public function getPaymentMethod()
    {
        if (!$this->paymentMethod) {
            if ($this->getBestellung()->Zahlungsart && strpos($this->getBestellung()->Zahlungsart->cModulId, "kPlugin_{$this::Plugin()->getID()}_") !== false) {
                $this->paymentMethod = LegacyMethod::create($this->getBestellung()->Zahlungsart->cModulId);
            } else {
                $this->paymentMethod = LegacyMethod::create("kPlugin_{$this::Plugin()->getID()}_mollie");
            }
        }
        return $this->paymentMethod;
    }

    /**
     * cancels oder refunds eine stornierte Bestellung
     *
     * @return string
     */
    abstract public function cancelOrRefund(): string;

    /**
     * @throws \JTL\Exceptions\CircularReferenceException
     * @throws \JTL\Exceptions\ServiceNotFoundException
     */
    public function handleNotification($hash = null)
    {

        if (!$hash) {
            $hash = $this->getModel()->getHash();
        }

        $this->updateModel()->saveModel();
        if (null === $this->getBestellung()->dBezahltDatum) {
            if ($incoming = $this->getIncomingPayment()) {
                $this->getPaymentMethod()->addIncomingPayment($this->getBestellung(), $incoming);
                if ($this->completlyPaid()) {
                    $this->getPaymentMethod()->setOrderStatusToPaid($this->getBestellung());
                    $this::makeFetchable($this->getBestellung(), $this->getModel());
                    $this->getPaymentMethod()->deletePaymentHash($hash);

                    $this->getPaymentMethod()->doLog(sprintf("Checkout::handleNotification: Bestellung '%s' als bezahlt markiert: %.2f %s", $this->getBestellung()->cBestellNr, (float)$incoming->fBetrag, $incoming->cISO), LOGLEVEL_NOTICE);

                    $oZahlungsart = Shop::Container()->getDB()->selectSingleRow('tzahlungsart', 'cModulId', $this->getPaymentMethod()->moduleID);
                    if ($oZahlungsart && (int)$oZahlungsart->nMailSenden === 1) {
                        $this->getPaymentMethod()->sendConfirmationMail($this->getBestellung());
                    }
                } else {
                    $this->getPaymentMethod()->doLog(sprintf("Checkout::handleNotification: Bestellung '%s': nicht komplett bezahlt: %.2f %s", $this->getBestellung()->cBestellNr, (float)$incoming->fBetrag, $incoming->cISO), LOGLEVEL_ERROR);
                }
            }
        }
    }

    /**
     * Speichert das Model
     *
     * @return bool
     * @throws Exception
     */
    public function saveModel(): bool
    {
        return $this->getModel()->save();
    }

    public function updateModel(): self
    {
        if ($this->getMollie()) {
            $this->getModel()->setOrderId($this->getMollie()->id);
            $this->getModel()->setLocale($this->getMollie()->locale);
            $this->getModel()->setAmount($this->getMollie()->amount->value);
            $this->getModel()->setMethod($this->getMollie()->method);
            $this->getModel()->setCurrency($this->getMollie()->amount->currency);
            $this->getModel()->setOrderId($this->getMollie()->id);
            $this->getModel()->setStatus($this->getMollie()->status);
        }
        $this->getModel()->setBestellung($this->oBestellung->kBestellung);
        $this->getModel()->setBestellNr($this->oBestellung->cBestellNr);
        $this->getModel()->setSynced($this->getModel()->getSynced() !== null ? $this->getModel()->getSynced() : self::Plugin()->getConfig()->getValue('onlyPaid') !== 'on');
        return $this;
    }

    /**
     * @return stdClass
     */
    abstract public function getIncomingPayment(): ?stdClass;

    public function completlyPaid(): bool
    {

        if ($row = Shop::Container()->getDB()->executeQueryPrepared("SELECT SUM(fBetrag) as fBetragSumme FROM tzahlungseingang WHERE kBestellung = :kBestellung", [
            ':kBestellung' => $this->oBestellung->kBestellung
        ], 1)) {
            return $row->fBetragSumme >= ($this->oBestellung->fGesamtsumme * (float)$this->oBestellung->fWaehrungsFaktor);
        }
        return false;

    }

    /**
     * @param Bestellung $oBestellung
     * @param OrderModel $model
     * @return bool
     * @throws \JTL\Exceptions\CircularReferenceException
     * @throws \JTL\Exceptions\ServiceNotFoundException
     */
    public static function makeFetchable(Bestellung $oBestellung, OrderModel $model): bool
    {
        if ($oBestellung->cAbgeholt === 'Y' && !$model->getSynced()) {
            Shop::Container()->getDB()->update('tbestellung', 'kBestellung', (int)$oBestellung->kBestellung, (object)['cAbgeholt' => 'N']);
            $model->setSynced(true);
            try {
                return $model->save(['synced']);
            } catch (Exception $e) {
                Shop::Container()->getLogService()->error(sprintf("Fehler beim speichern des Models: %s / Bestellung: %s", $model->getId(), $oBestellung->cBestellNr));
            }
        }
        return false;
    }

    /**
     * @param array $paymentOptions
     * @return Payment|Order
     */
    abstract public function create(array $paymentOptions = []);

    /**
     * @return string
     */
    public function getHash(): string
    {
        if ($this->getModel()->getHash()) {
            return $this->getModel()->getHash();
        }
        if (!$this->hash) {
            $this->hash = $this->getPaymentMethod()->generateHash($this->oBestellung);
        }
        return $this->hash;
    }

    /**
     * @throws Exception
     * @todo
     */
    public function storno(): void
    {
        /*if (in_array((int)$this->getBestellung()->cStatus, [BESTELLUNG_STATUS_OFFEN, BESTELLUNG_STATUS_IN_BEARBEITUNG], true)) {

            $log = [];

            $conf = Shop::getSettings([CONF_GLOBAL, CONF_TRUSTEDSHOPS]);
            $nArtikelAnzeigefilter = (int)$conf['global']['artikel_artikelanzeigefilter'];

            foreach ($this->getBestellung()->Positionen as $pos) {
                if ($pos->kArtikel && $pos->Artikel && $pos->Artikel->cLagerBeachten === 'Y') {
                    $log[] = sprintf('Reset stock of "%s" by %d', $pos->Artikel->cArtNr, -1 * $pos->nAnzahl);
                    self::aktualisiereLagerbestand($pos->Artikel, -1 * $pos->nAnzahl, $pos->WarenkorbPosEigenschaftArr, $nArtikelAnzeigefilter);
                }
            }
            $log[] = sprintf("Cancel order '%s'.", $this->getBestellung()->cBestellNr);

            if (Shop::DB()->executeQueryPrepared('UPDATE tbestellung SET cAbgeholt = "N", cStatus = :cStatus WHERE kBestellung = :kBestellung', [':cStatus' => '-1', ':kBestellung' => $this->getBestellung()->kBestellung], 3)) {
                $this->Log(implode('\n', $log));
            }
        }*/
    }

}