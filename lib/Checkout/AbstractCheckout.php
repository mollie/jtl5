<?php


namespace Plugin\ws5_mollie\lib\Checkout;


use Exception;
use JTL\Catalog\Currency;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Bestellung;
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
use RuntimeException;
use SmartyException;
use stdClass;

abstract class AbstractCheckout
{
    use Plugin;

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
    public static function fromID($id): AbstractCheckout
    {
        $model = OrderModel::loadByAttributes([
            'orderId' => $id,
        ], Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_FAIL);
        $oBestellung = new Bestellung($model->getBestellung(), true);

        $self = new static($oBestellung, new MollieAPI($model->getTest()));
        $self->setModel($model);
        return $self;
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
                Shop::Container()->getBackendLogService()->error(sprintf("Fehler beim speichern des Models: %s / Bestellung: %s", $model->getId(), $oBestellung->cBestellNr));
            }
        }
        return false;
    }

    /**
     * @return Bestellung
     * @throws Exception
     */
    public function getBestellung(): Bestellung
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

    /**
     * @param array $paymentOptions
     * @return Payment|Order
     */
    abstract public function create(array $paymentOptions = []);

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

    abstract public function getMollie($force = false);

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
     * @return \Plugin\ws5_mollie\lib\PaymentMethod
     */
    public function getPaymentMethod(): \Plugin\ws5_mollie\lib\PaymentMethod
    {
        if (!$this->paymentMethod) {
            $this->paymentMethod = LegacyMethod::create($this->oBestellung->Zahlungsart->cModulId);
            if (!$this->paymentMethod) {
                throw new RuntimeException('Could not load PaymentMethod!');
            }
        }
        return $this->paymentMethod;
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

    public function setRequestData(string $key, $value): self
    {
        if (!$this->reqData) {
            $this->reqData = [];
        }
        $this->reqData[$key] = $value;
        return $this;
    }

    public function getRequestData(): array
    {
        return !$this->reqData ? $this->loadRequest()->reqData : $this->reqData;
    }

    abstract public function loadRequest(array $options = []): AbstractCheckout;

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

        $remindables = Shop::Container()->getDB()->executeQueryPrepared('SELECT kId FROM xplugin_ws5_mollie_orders WHERE dReminder IS NULL AND dCreated < NOW() - INTERVAL :d HOUR AND cStatus IN ("created","open", "expired", "failed", "canceled")', [
            ':d' => $reminder
        ], 2);
        foreach ($remindables as $remindable) {
            self::sendReminder($remindable->kId);
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
        $data->tkunde = new \JTL\Customer\Customer($oBestellung->kKunde);
        $data->Bestellung = $oBestellung;
        $data->PayURL = $repayURL;
        $data->Amount = Preise::getLocalizedPriceString($order->getAmount(), Currency::fromISO($order->getCurrency()), false);

        $mailer = Shop::Container()->get(Mailer::class);
        $mail = new Mail();
        $mail->createFromTemplateID('kPlugin_' . self::Plugin()->getID() . '_zahlungserinnerung', $data);

        $order->setReminder(date('Y-m-d H:i:s'));
        $order->save(['reminder']);

        if (!$mailer->send($mail)) {
            throw new Exception($mail->getError() . "\n" . print_r($order->rawArray(), 1));
        }
        return true;
    }

}