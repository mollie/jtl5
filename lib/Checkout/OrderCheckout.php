<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Checkout;

use DateTime;
use DateTimeZone;
use Exception;
use JTL\Cart\CartItem;
use JTL\Helpers\Tax;
use JTL\Helpers\Text;
use JTL\Session\Frontend;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
use Plugin\ws5_mollie\lib\Order\Address;
use Plugin\ws5_mollie\lib\Order\OrderLine as WSOrderLine;
use RuntimeException;
use Shop;
use stdClass;

/**
 * Class OrderCheckout
 * @package Plugin\ws5_mollie\lib\Checkout
 *
 * @property string $orderNumber
 * @property Address $billingAddress
 * @property null|Address $shippingAddress
 * @property null|string $consumerDateOfBirth
 * @property WSOrderLine[] $lines
 * @property null|string $expiresAt
 * @property null|array $payment
 */
class OrderCheckout extends AbstractCheckout
{
    /** @var Order */
    protected $order;

    /** @var Payment */
    protected $mollie;

    /**
     * @param array $paymentOptions
     * @return Order
     * @throws Exception
     */
    public function create(array $paymentOptions = []): Order
    {
        if ($this->getModel()->orderId) {
            try {
                $this->order = $this->getAPI()->getClient()->orders->get($this->getModel()->orderId, ['embed' => 'payments']);
                if (in_array($this->order->status, [OrderStatus::STATUS_COMPLETED, OrderStatus::STATUS_PAID, OrderStatus::STATUS_AUTHORIZED, OrderStatus::STATUS_PENDING], true)) {
                    throw new RuntimeException(self::Plugin()->getLocalization()->getTranslation('errAlreadyPaid'));
                }
                if ($this->order->status === OrderStatus::STATUS_CREATED) {
                    if ($this->order->payments()) {
                        /** @var Payment $payment */
                        foreach ($this->order->payments() as $payment) {
                            if ($payment->status === PaymentStatus::STATUS_OPEN) {
                                $this->mollie = $payment;

                                break;
                            }
                        }
                    }
                    if (!$this->mollie) {
                        $this->mollie = $this->getAPI()->getClient()->orderPayments->createForId($this->getModel()->cOrderId, $paymentOptions);
                    }
                    $this->updateModel()->saveModel();

                    return $this->getMollie(true);
                }
            } catch (Exception $e) {
                $this->Log(sprintf("OrderCheckout::create: Letzte Order '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->cOrderId, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $this->order = $this->getAPI()->getClient()->orders->create($this->loadRequest($paymentOptions)->jsonSerialize());
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->Log(sprintf("OrderCheckout::create: Neue Order '%s' konnte nicht erstellt werden: %s.", $this->oBestellung->cBestellNr, $e->getMessage()), LOGLEVEL_ERROR);
            throw new RuntimeException(sprintf("OrderCheckout::create: Neue Order '%s' konnte nicht erstellt werden: %s.", $this->oBestellung->cBestellNr, $e->getMessage()));
        }

        return $this->order;
    }

    /**
     * @return static
     * @throws Exception
     *
     */
    public function updateModel(): AbstractCheckout
    {
        parent::updateModel();
        if (!$this->mollie && $this->getMollie() && $this->getMollie()->payments()) {
            /** @var Payment $payment */
            foreach ($this->getMollie()->payments() as $payment) {
                if (in_array($payment->status, [PaymentStatus::STATUS_OPEN, PaymentStatus::STATUS_PENDING, PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
                    $this->mollie = $payment;

                    break;
                }
            }
        }
        if ($this->mollie) {
            $this->getModel()->cTransactionId = $this->mollie->id;
        }
        $this->getModel()->cStatus = $this->getMollie()->status;
        $this->getModel()->cHash = $this->getHash();
        $this->getModel()->fAmountRefunded = $this->getMollie()->amountRefunded->value ?? 0;

        return $this;
    }

    /**
     * @param bool $force
     * @return Order
     * @throws Exception
     */
    public function getMollie($force = false): ?Order
    {
        if ($force || (!$this->order && $this->getModel()->cOrderId)) {
            try {
                $this->order = $this->getAPI()->getClient()->orders->get($this->getModel()->cOrderId, ['embed' => 'payments,shipments,refunds']);
            } catch (Exception $e) {
                throw new RuntimeException(sprintf('Mollie-Order \'%s\' konnte nicht geladen werden: %s', $this->getModel()->cOrderId, $e->getMessage()));
            }
        }

        return $this->order;
    }

    /**
     * @param Order|Payment $model
     *
     * @return static
     */
    protected function setMollie($model)
    {
        $this->order = $model;

        return $this;
    }

    /**
     * @param array $options
     * @return self
     * @throws Exception
     */
    public function loadRequest(array &$options = [])
    {
        parent::loadRequest($options);

        $this->orderNumber = $this->getBestellung()->cBestellNr;
        $this->billingAddress = new Address($this->getBestellung()->oRechnungsadresse);
        if ($this->getBestellung()->Lieferadresse !== null) {
            if (!$this->getBestellung()->Lieferadresse->cMail) {
                $this->getBestellung()->Lieferadresse->cMail = $this->getBestellung()->oRechnungsadresse->cMail;
            }
            $this->shippingAddress = new Address($this->getBestellung()->Lieferadresse);
        }

        if (
            !empty(Frontend::getCustomer()->dGeburtstag)
            && Frontend::getCustomer()->dGeburtstag !== '0000-00-00'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim(Frontend::getCustomer()->dGeburtstag))
        ) {
            $this->consumerDateOfBirth = trim(Frontend::getCustomer()->dGeburtstag);
        }

        $lines = [];

        //$Positionen = $this->getPaymentMethod()->duringCheckout ? $_SESSION['Warenkorb']->PositionenArr : $this->getBestellung()->Positionen;

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

        if (!defined('MOLLIE_DEFAULT_MAX_EXPIRY_LIMIT')) {
            define('MOLLIE_DEFAULT_MAX_EXPIRY_LIMIT', 100);
        }

        if (!defined('MOLLIE_KLARNA_MAX_EXPIRY_LIMIT')) {
            define('MOLLIE_KLARNA_MAX_EXPIRY_LIMIT', 28);
        }

        if (($dueDays = (int)self::Plugin()->getConfig()->getValue($this->getPaymentMethod()->moduleID . '_dueDays')) && $dueDays > 0) {
            $max = $this->method && strpos($this->method, 'klarna') !== false ? MOLLIE_KLARNA_MAX_EXPIRY_LIMIT : MOLLIE_DEFAULT_MAX_EXPIRY_LIMIT;
            $date = new DateTime(sprintf('+%d DAYS', min($dueDays, $max)), new DateTimeZone('UTC'));
            $this->expiresAt = $date->format('Y-m-d');
        }

        $this->payment = $options;

        return $this;
    }

    /**
     * @return CartItem[]
     *
     * @psalm-return array<CartItem>
     * @throws Exception
     */
    public function getPositionen(): array
    {
        if ($this->getPaymentMethod()->duringCheckout) {
            $conf = Shop::getSettings([CONF_GLOBAL]);
            $oPositionenArr = [];

            if (is_array($_SESSION['Warenkorb']->PositionenArr) && count($_SESSION['Warenkorb']->PositionenArr) > 0) {
                $productFilter = (int)$conf['global']['artikel_artikelanzeigefilter'];
                /** @var CartItem $item */
                foreach ($_SESSION['Warenkorb']->PositionenArr as $item) {
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
                                    $WKPosEigenschaft->kWarenkorbPos = $item->kWarenkorbPos;
                                    $WKPosEigenschaft->cEigenschaftName = $WKPosEigenschaft->cEigenschaftName[$idx];
                                    $WKPosEigenschaft->cEigenschaftWertName = $WKPosEigenschaft->cEigenschaftWertName[$idx];
                                    $WKPosEigenschaft->cFreifeldWert = $WKPosEigenschaft->cEigenschaftWertName;
                                }
                            }
                        } else {
                            foreach ($item->WarenkorbPosEigenschaftArr as $o => $WKPosEigenschaft) {
                                $WKPosEigenschaft->kWarenkorbPos = $item->kWarenkorbPos;
                                $WKPosEigenschaft->cEigenschaftName = $WKPosEigenschaft->cEigenschaftName[$idx];
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
     * @return null|stdClass
     * @throws Exception
     */
    public function getIncomingPayment(): ?stdClass
    {
        /** @var Payment $payment */
        foreach ($this->getMollie()->payments() as $payment) {
            if (
                in_array(
                    $payment->status,
                    [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID],
                    true
                )
            ) {
                $this->mollie = $payment;

                return (object)[
                    'fBetrag' => (float)$payment->amount->value,
                    'cISO' => $payment->amount->currency,
                    'cZahler' => $payment->details->paypalPayerId ?? $payment->customerId,
                    'cHinweis' => $payment->details->paypalReference ?? $payment->id,
                ];
            }
        }

        return null;
    }

    /**
     * @return string
     * @throws ApiException
     */
    public function cancelOrRefund(): string
    {
        if ((int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_STORNO) {
            if ($this->getMollie()->isCancelable) {
                $res = $this->getMollie()->cancel();

                return 'Order cancelled, Status: ' . $res->status;
            }
            $res = $this->getMollie()->refundAll();

            return 'Order Refund initiiert, Status: ' . $res->status;
        }

        throw new Exception('Bestellung ist derzeit nicht storniert, Status: ' . $this->getBestellung()->cStatus);
    }

    /**
     * @return static
     * @throws \JTL\Exceptions\CircularReferenceException
     * @throws \JTL\Exceptions\ServiceNotFoundException
     */
    protected function updateOrderNumber()
    {
        try {
            if ($this->getMollie()) {
                $this->getMollie()->orderNumber = $this->getBestellung()->cBestellNr;
                $this->getMollie()->webhookUrl = Shop::getURL() . '/?mollie=1';
                $this->getMollie()->update();
            }
            if ($this->getModel()->cTransactionId) {
                $this->getAPI()->getClient()->payments->update($this->getModel()->cTransactionId, [
                    'description' => $this->getDescription()
                ]);
            }
        } catch (Exception $e) {
            $this->Log('OrderCheckout::updateOrderNumber:' . $e->getMessage(), LOGLEVEL_ERROR);
        }

        return $this;
    }
}
