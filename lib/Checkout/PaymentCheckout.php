<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Checkout;

use Exception;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Shop;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\PluginHelper;
use RuntimeException;
use stdClass;
use Plugin\ws5_mollie\lib\Order\Address;
use Plugin\ws5_mollie\lib\Payment\OrderLine as WSOrderLine;
use Mollie\Api\Types\PaymentMethod;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Cart\CartItem;


/**
 * Class PaymentCheckout
 * @package Plugin\ws5_mollie\lib\Checkout
 * @property string $description
 * @property string $customerId
 * @property null|string $captureMode
 * @property null|Address $billingAddress
 * @property null|Address $shippingAddress
 * @property null|WSOrderLine[] $lines

 */
class PaymentCheckout extends AbstractCheckout
{
    protected $payment;

    /**
     * @param array $paymentOptions
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @return Payment
     */
    public function create(array $paymentOptions = []): Payment
    {
        if ($this->getModel()->cOrderId) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($this->getModel()->cOrderId);
                if (in_array($this->payment->status, [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
                    $this->Log(PluginHelper::getPlugin()->getLocalization()->getTranslation('errAlreadyPaid'));

                    return $this->payment;
                }
                if ($this->payment->status === PaymentStatus::STATUS_OPEN) {
                    $this->updateModel()->updateModel();

                    return $this->payment;
                }
            } catch (Exception $e) {
                $this->Log(sprintf("PaymentCheckout::create: Letzte Transaktion '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->cOrderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $req           = $this->loadRequest($paymentOptions)->jsonSerialize();
            $this->payment = $this->getAPI()->getClient()->payments->create($req);
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->Log(sprintf("PaymentCheckout::create: Neue Transaktion '%s' konnte nicht erstellt werden: %s.\n%s", $this->oBestellung->cBestellNr, $e->getMessage(), json_encode($req)), LOGLEVEL_ERROR);

            throw new RuntimeException(sprintf('Mollie-Payment \'%s\' konnte nicht geladen werden: %s', $this->getModel()->cOrderId, $e->getMessage()));
        }

        return $this->payment;
    }

    /**
     * @throws Exception
     *
     * @return static
     */
    public function updateModel(): AbstractCheckout
    {
        parent::updateModel();
        $this->getModel()->cHash           = $this->getHash();
        $this->getModel()->fAmountRefunded = $this->getMollie()->amountRefunded->value ?? 0;

        return $this;
    }

    /**
     * @param mixed $force
     * @throws Exception
     * @throws Exception
     * @return Payment
     */
    public function getMollie($force = false): ?Payment
    {
        if ($force || (!$this->payment && $this->getModel()->cOrderId)) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($this->getModel()->cOrderId, ['embed' => 'refunds']);
            } catch (Exception $e) {
                throw new RuntimeException('Mollie-Payment konnte nicht geladen werden: ' . $e->getMessage());
            }
        }

        return $this->payment;
    }

    /**
     * @param array $options
     * @throws Exception
     * @return $this
     */
    public function loadRequest(array &$options = []): static
    {
        parent::loadRequest($options);

        // Set description as it is specified in plugin settings - for "Bezahlung vor Bestellabschluss" this is overwritten later in updateOrderNumber()
        $this->description = $this->getDescription();

        // Set Method-specific parameters that are filled according to payment method (overwrites description for paypal and KBC)
        foreach ($options as $key => $value) {
            $this->$key = $value;
        }

        // captureMode manual is required for riverty and optional but requested by mollie for the others: https://docs.mollie.com/docs/place-a-hold-for-a-payment
        if (in_array($this->method, [PaymentMethod::KLARNA_ONE, PaymentMethod::KLARNA_SLICE_IT, PaymentMethod::KLARNA_PAY_LATER, PaymentMethod::KLARNA_PAY_NOW, PaymentMethod::BILLIE, PaymentMethod::RIVERTY])) {
            // Set CaptureMode to "manual" for Riverty according to Mollie Api Docs
            $this->captureMode = 'manual';
        }

        // Set additional parameters for all payments since v2.0.0
        $this->billingAddress = new Address($this->getBestellung()->oRechnungsadresse);
        if ($this->getBestellung()->Lieferadresse !== null) {
            if (!$this->getBestellung()->Lieferadresse->cMail) {
                $this->getBestellung()->Lieferadresse->cMail = $this->getBestellung()->oRechnungsadresse->cMail;
            }
            $this->shippingAddress = new Address($this->getBestellung()->Lieferadresse);
        }

        $lines = [];

        $Positionen = $this->getPositionen();

        foreach ($Positionen as $oPosition) {
            $lines[] = WSOrderLine::factory($oPosition, $this->getBestellung()->Waehrung);
        }

        if ($this->getBestellung()->GuthabenNutzen && $this->getBestellung()->fGuthaben > 0) {
            $lines[] = WSOrderLine::getCredit($this->getBestellung());
        }

        if ($comp = WSOrderLine::getRoundingCompensation($lines, $this->amount, $this->getBestellung()->Waehrung)) {
            $lines[] = $comp;
        }
        $this->lines = $lines;


        return $this;
    }

    /**
     * @throws Exception
     * @return CartItem[]
     *
     * @psalm-return array<CartItem>
     */
    public function getPositionen(): array
    {
        if ($this->getPaymentMethod()->duringCheckout) {
            $conf           = Shop::getSettings([CONF_GLOBAL]);
            $oPositionenArr = [];

            if (is_array($_SESSION['Warenkorb']->PositionenArr) && count($_SESSION['Warenkorb']->PositionenArr) > 0) {
                $productFilter = (int)$conf['global']['artikel_artikelanzeigefilter'];
                /** @var CartItem $item */
                foreach ($_SESSION['Warenkorb']->PositionenArr as $_item) {
                    $item = unserialize(serialize($_item));

                    $item->cName = Text::unhtmlentities(is_array($item->cName)
                        ? $item->cName[$_SESSION['cISOSprache']]
                        : $item->cName);

                    $item->fMwSt = Tax::getSalesTax($item->kSteuerklasse);
                    if (is_array($item->WarenkorbPosEigenschaftArr) && count($item->WarenkorbPosEigenschaftArr) > 0) {
                        $idx = Shop::getLanguageCode();
                        // Bei einem Varkombikind dürfen nur FREIFELD oder PFLICHT-FREIFELD gespeichert werden,
                        // da sonst eventuelle Aufpreise in der Wawi doppelt berechnet werden
                        if (isset($item->Artikel->kVaterArtikel) && $item->Artikel->kVaterArtikel > 0) {
                            foreach ($item->WarenkorbPosEigenschaftArr as $o => $WKPosEigenschaft) {
                                if ($WKPosEigenschaft->cTyp === 'FREIFELD' || $WKPosEigenschaft->cTyp === 'PFLICHT-FREIFELD') {
                                    $WKPosEigenschaft->kWarenkorbPos        = $item->kWarenkorbPos;
                                    $WKPosEigenschaft->cEigenschaftName     = $WKPosEigenschaft->cEigenschaftName[$idx];
                                    $WKPosEigenschaft->cEigenschaftWertName = $WKPosEigenschaft->cEigenschaftWertName[$idx];
                                    $WKPosEigenschaft->cFreifeldWert        = $WKPosEigenschaft->cEigenschaftWertName;
                                }
                            }
                        } else {
                            foreach ($item->WarenkorbPosEigenschaftArr as $o => $WKPosEigenschaft) {
                                $WKPosEigenschaft->kWarenkorbPos        = $item->kWarenkorbPos;
                                $WKPosEigenschaft->cEigenschaftName     = $WKPosEigenschaft->cEigenschaftName[$idx];
                                $WKPosEigenschaft->cEigenschaftWertName = $WKPosEigenschaft->cEigenschaftWertName[$idx];
                                if ($WKPosEigenschaft->cTyp === 'FREIFELD' || $WKPosEigenschaft->cTyp === 'PFLICHT-FREIFELD') {
                                    $WKPosEigenschaft->cFreifeldWert = $WKPosEigenschaft->cEigenschaftWertName;
                                }
                            }
                        }
                    }
                    $oPositionenArr[] = $item;
                }
            }

            return $oPositionenArr;
        }

        return $this->getBestellung()->Positionen;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function capturePayment(): string
    {
        if ($this->getBestellung()->kBestellung) {
            $oKunde = $this->getBestellung()->oKunde ?? new \JTL\Customer\Customer($this->getBestellung()->kKunde);

            $shippingActive = PluginHelper::getSetting('shippingActive');
            if ($shippingActive === 'N') {
                return 'Capture deaktiviert: ' . $this->getModel()->cOrderId;
            }

            if ($shippingActive === 'K' && !$oKunde->nRegistriert && (int)$this->getBestellung()->cStatus !== BESTELLUNG_STATUS_VERSANDT) {
                return 'Capture für Gast-Bestellungen und Teilversand deaktiviert: ' . $this->getModel()->cOrderId;
            }

            foreach ($this->getBestellung()->oLieferschein_arr as $oLieferschein) {
                try {
                    $mode = PluginHelper::getSetting('shippingMode');
                    switch ($mode) {
                        case 'A':
                            // Capture directly
                            return $this->capture();
                        case 'B':
                            // only Capture if complete shipping
                            if ($oKunde->nRegistriert || (int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_VERSANDT) {
                                return $this->capture();
                            }

                            return 'Gastbestellung noch nicht komplett versendet: ' . $this->getModel()->cOrderId;
                    }
                } catch (APIException|Exception $e) {
                    Shop::Container()->getLogService()->error("mollie: PaymentCheckout:capturePayment (BestellNr. {$this->getBestellung()->cBestellNr}, Lieferschein: {$oLieferschein->getLieferscheinNr()}) - " . $e->getMessage());
                }
            }
        }

        return 'Error: Payment not captured: ' . $this->getModel()->cOrderId;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function capture(): string
    {
        try {
            $captures = $this->getAPI()->getClient()->paymentCaptures->listFor($this->payment);
            if ($captures->count > 0) {
                return 'Payment already captured: ' . $this->getModel()->cOrderId;
            } else {
                $capture = $this->getAPI()->getClient()->paymentCaptures->createFor($this->payment);
                if ($capture) {
                    $this->Log(sprintf("Checkout::capturePayment: Capture der Bestellung '%s' an Mollie gemeldet: %.2f", $this->getBestellung()->cBestellNr, $capture->amount->value));
                    return 'Payment captured: ' . $capture->paymentId . ' | Amount: ' . $capture->amount->value . ' | captureID: ' . $capture->id;
                }
            }

            return 'Error: Payment not captured: ' . $this->getModel()->cOrderId;
        } catch (\Exception $e) {
            return 'Error: Payment not captured: ' . $this->getModel()->cOrderId . ' | ErrorMessage: ' . $e->getMessage();
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public function releaseAuthorization(): string
    {
        if (!is_null($this->getMollie())) {
            if ($this->getMollie()->isAuthorized()) {
                $this->getAPI()->getClient()->payments->releaseAuthorization($this->getMollie()->id);
                $status = $this->getMollie(true)->status;
                if ($status === PaymentStatus::STATUS_CANCELED) {
                    PluginHelper::getDB()->executeQueryPrepared('UPDATE tbestellung SET cStatus = -1 WHERE kBestellung = :kBestellung',
                        [
                            ':kBestellung' => $this->getBestellung()->kBestellung
                        ], 10);

                }

                return 'Released Payment authorization.';
            }

            return 'Payment status invalid for releasing authorization: ' . $this->getMollie()->id;
        } else {
            throw new Exception('Mollie Payment zur Bestellung (' .  $this->getBestellung()->cBestellNr  . ') konnte nicht geladen werden.');
        }

        throw new RuntimeException('Bestellung konnte nicht storniert werden: ' . $this->getBestellung()->cBestellNr);
    }

    /**
     * @throws Exception
     * @return null|stdClass
     */
    public function getIncomingPayment(): ?stdClass
    {
        if (in_array($this->getMollie()->status, [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
            $data             = [];
            $data['fBetrag']  = (float)$this->getMollie()->amount->value;
            $data['cISO']     = $this->getMollie()->amount->currency;
            $data['cZahler']  = $this->getMollie()->details->paypalPayerId   ?? $this->getMollie()->customerId;
            $data['cHinweis'] = $this->getMollie()->details->paypalReference ?? $this->getMollie()->id;

            return (object)$data;
        }

        return null;
    }

    /**
     * @throws ApiException
     * @throws IncompatiblePlatform
     * @throws RuntimeException
     * @throws Exception
     * @return string
     */
    public function cancelOrRefund(): string
    {
        if ((int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_STORNO) {
            if (!is_null($this->getMollie())) {
                if ($this->getMollie()->isCancelable) {
                    $res = $this->getAPI()->getClient()->payments->cancel($this->getMollie()->id);

                    return 'Payment cancelled, Status: ' . $res->status;
                } elseif ($this->getMollie()->isAuthorized() && $this->getMollie()->captureMode === "manual") {
                    $this->getAPI()->getClient()->payments->releaseAuthorization($this->getMollie()->id);
                    $status = $this->getMollie(true)->status;
                    return 'Payment cancelled, Status: ' . $status;
                }
                $res = $this->getAPI()->getClient()->payments->refund($this->getMollie(), ['amount' => $this->getMollie()->amount]);

                return 'Payment Refund initiiert, Status: ' . $res->status;
            } else {
                throw new Exception('Mollie Payment zur Bestellung (' .  $this->getBestellung()->cBestellNr  . ') konnte nicht geladen werden.');
            }
        }

        throw new RuntimeException('Bestellung ist derzeit nicht storniert, Status: ' . $this->getBestellung()->cStatus);
    }

    /**
     * @param Order|Payment $model
     *
     * @return static
     */
    protected function setMollie($model)
    {
        $this->payment = $model;

        return $this;
    }

    /**
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @return static
     */
    protected function updateOrderNumber()
    {
        //only ordernumber
        try {
            if ($this->getMollie()) {
                $this->getMollie()->description = $this->getDescription();
                $this->getMollie()->update(true);
            }
        } catch (Exception $e) {
            $this->Log('OrderCheckout::updateOrderNumber:' . $e->getMessage(), LOGLEVEL_ERROR);
        }

        try {
            if ($this->getMollie()) {
                $this->getMollie()->description = $this->getDescription();
                $this->getMollie()->webhookUrl  = Shop::getURL() . '/?mollie=1';
                $this->getMollie()->update();
            }
        } catch (Exception $e) {
            $this->Log('OrderCheckout::updateOrderNumber:' . $e->getMessage(), LOGLEVEL_ERROR);
        }

        return $this;
    }
}
