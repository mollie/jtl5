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
use Mollie\Api\Exceptions\IncompatiblePlatformException;
use Mollie\Api\Http\Requests\UpdatePaymentRequest;
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
        $paymentId = $this->getPaymentResourceId();
        if ($paymentId) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($paymentId);
                if (in_array($this->payment->status, [PaymentStatus::AUTHORIZED, PaymentStatus::PAID], true)) {
                    $this->Log(PluginHelper::getPlugin()->getLocalization()->getTranslation('errAlreadyPaid'));

                    return $this->payment;
                }
                if ($this->payment->status === PaymentStatus::OPEN) {
                    $this->updateModel()->saveModel();

                    return $this->payment;
                }
            } catch (\Throwable $e) {
                $this->Log(sprintf("PaymentCheckout::create: Letzte Transaktion '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $paymentId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        $request = null;
        try {
            $this->loadRequest($paymentOptions);
            $request = PaymentRequestBuilder::fromCheckout($this, $paymentOptions);
            $this->payment = $this->getAPI()->getClient()->send($request);
            // Repay of legacy ord_*: store the new payment as primary resource id
            if (self::isLegacyOrderId((string)$this->getModel()->cOrderId)) {
                $this->getModel()->cTransactionId = $this->payment->id;
            }
            $this->updateModel()->saveModel();
        } catch (\Throwable $e) {
            $payloadLog = $request ? json_encode(PaymentRequestBuilder::debugPayload($request)) : json_encode($paymentOptions);
            $this->Log(sprintf("PaymentCheckout::create: Neue Transaktion '%s' konnte nicht erstellt werden: %s.\n%s", $this->oBestellung->cBestellNr, $e->getMessage(), $payloadLog), LOGLEVEL_ERROR);

            throw new RuntimeException(sprintf('Mollie-Payment \'%s\' konnte nicht geladen werden: %s', $this->getModel()->cOrderId, $e->getMessage()), 0, $e);
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
        $this->getModel()->fAmountRefunded = $this->getMollie()?->amountRefunded?->value ?? 0;

        return $this;
    }

    /**
     * @param mixed $force
     * @throws Exception
     * @return Payment|null
     */
    public function getMollie($force = false): ?Payment
    {
        $paymentId = $this->getPaymentResourceId();
        if ($paymentId === null) {
            return $this->payment;
        }

        if ($force || !$this->payment) {
            try {
                $this->payment = $this->getAPI()->getClient()->payments->get($paymentId, ['embed' => 'refunds']);
            } catch (\Throwable $e) {
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
            try {
                $payment = $this->getMollie(true);
                if ($this->isOrderLinkedPayment($payment)) {
                    return self::LEGACY_ORDER_SHIPMENT_MESSAGE;
                }
            } catch (\Throwable $e) {
                // continue — capture() handles load errors
            }

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
                } catch (\Throwable $e) {
                    Shop::Container()->getLogService()->error("mollie: PaymentCheckout:capturePayment (BestellNr. {$this->getBestellung()->cBestellNr}, Lieferschein: {$oLieferschein->getLieferscheinNr()}) - " . $e->getMessage());
                }
            }
        }

        return 'Error: Payment not captured: ' . $this->getModel()->cOrderId;
    }

    /**
     * Capture a manual-capture payment.
     * Order-linked / legacy ord_* payments cannot be captured via Payment Captures API
     * (Mollie requires Shipments API → Dashboard).
     *
     * @return string
     */
    public function capture(): string
    {
        try {
            $paymentId = $this->getPaymentResourceId();
            if ($paymentId === null) {
                return self::LEGACY_ORDER_DEGRADE_MESSAGE
                    . ' Capture nicht möglich ohne Payment-ID (cTransactionId).';
            }

            $payment = $this->getMollie(true);
            if ($payment === null) {
                return 'Error: Payment not captured: ' . $this->getModel()->cOrderId
                    . ' | ErrorMessage: Payment konnte nicht geladen werden.';
            }

            if ($this->isOrderLinkedPayment($payment)) {
                return self::LEGACY_ORDER_SHIPMENT_MESSAGE;
            }

            $captures = $this->getAPI()->getClient()->paymentCaptures->pageFor($payment);
            if (count($captures) > 0) {
                return 'Payment already captured: ' . $this->getModel()->cOrderId;
            }

            $description = $this->getDescription();
            if ($description === '') {
                $description = 'Order ' . ($this->getBestellung()->cBestellNr ?: $payment->id);
            }

            $capture = $this->getAPI()->getClient()->paymentCaptures->createFor($payment, [
                'description' => $description,
            ]);
            if ($capture) {
                $this->Log(sprintf(
                    "Checkout::capturePayment: Capture der Bestellung '%s' an Mollie gemeldet: %.2f",
                    $this->getBestellung()->cBestellNr,
                    $capture->amount->value
                ));

                return 'Payment captured: ' . $capture->paymentId
                    . ' | Amount: ' . $capture->amount->value
                    . ' | captureID: ' . $capture->id;
            }

            return 'Error: Payment not captured: ' . $this->getModel()->cOrderId;
        } catch (\Throwable $e) {
            return 'Error: Payment not captured: ' . $this->getModel()->cOrderId
                . ' | ErrorMessage: ' . $e->getMessage();
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public function releaseAuthorization(): string
    {
        $payment = $this->getMollie();
        if ($payment === null) {
            throw new Exception('Mollie Payment zur Bestellung (' . $this->getBestellung()->cBestellNr . ') konnte nicht geladen werden.');
        }

        if ($payment->isAuthorized()) {
            $this->getAPI()->getClient()->payments->releaseAuthorization($payment->id);
            $status = $this->getMollie(true)?->status;
            if ($status === PaymentStatus::CANCELED) {
                PluginHelper::getDB()->executeQueryPrepared('UPDATE tbestellung SET cStatus = -1 WHERE kBestellung = :kBestellung',
                    [
                        ':kBestellung' => $this->getBestellung()->kBestellung
                    ], 10);
            }

            return 'Released Payment authorization.';
        }

        return 'Payment status invalid for releasing authorization: ' . $payment->id;
    }

    /**
     * @throws Exception
     * @return null|stdClass
     */
    public function getIncomingPayment(): ?stdClass
    {
        $payment = $this->getMollie();
        if ($payment === null || !in_array($payment->status, [PaymentStatus::AUTHORIZED, PaymentStatus::PAID], true)) {
            return null;
        }

        $cHinweis = $payment->id;
        if (isset($payment->details->paypalReference) && PluginHelper::getSetting('paypalID') === 'paypal') {
            $cHinweis = $payment->details->paypalReference;
        }

        return (object)[
            'fBetrag'  => (float)$payment->amount->value,
            'cISO'     => $payment->amount->currency,
            'cZahler'  => $payment->details->paypalPayerId ?? $payment->customerId,
            'cHinweis' => $cHinweis,
        ];
    }

    /**
     * @throws ApiException
     * @throws IncompatiblePlatformException
     * @throws RuntimeException
     * @throws Exception
     * @return string
     */
    public function cancelOrRefund(): string
    {
        if ((int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_STORNO) {
            $payment = $this->getMollie();
            if ($payment === null) {
                throw new Exception('Mollie Payment zur Bestellung (' . $this->getBestellung()->cBestellNr . ') konnte nicht geladen werden.');
            }

            if ($payment->isCancelable) {
                $res = $this->getAPI()->getClient()->payments->cancel($payment->id);

                return 'Payment cancelled, Status: ' . $res->status;
            }

            if ($payment->isAuthorized() && $payment->captureMode === 'manual') {
                $this->getAPI()->getClient()->payments->releaseAuthorization($payment->id);
                $status = $this->getMollie(true)?->status;

                return 'Payment cancelled, Status: ' . $status;
            }

            $res = $this->getAPI()->getClient()->payments->refund($payment, [
                'amount' => [
                    'currency' => $payment->amount->currency,
                    'value' => $payment->amount->value,
                ],
            ]);

            return 'Payment Refund initiiert, Status: ' . $res->status;
        }

        throw new RuntimeException('Bestellung ist derzeit nicht storniert, Status: ' . $this->getBestellung()->cStatus);
    }

    /**
     * @param Payment $model
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
        try {
            $payment = $this->getMollie();
            if ($payment) {
                $this->getAPI()->getClient()->send(
                    new UpdatePaymentRequest(
                        id: $payment->id,
                        description: $this->getDescription(),
                        webhookUrl: Shop::getURL() . '/?mollie=1',
                    )
                );
                $this->getMollie(true);
            }
        } catch (\Throwable $e) {
            $this->Log('PaymentCheckout::updateOrderNumber:' . $e->getMessage(), LOGLEVEL_ERROR);
        }

        return $this;
    }
}
