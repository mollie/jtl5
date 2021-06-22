<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Order;

use Bestellung;
use Currency;
use Exception;
use JTL\Cart\CartItem;
use JTL\Cart\CartItemProperty;
use Mollie\Api\Types\OrderLineType;
use Plugin\ws5_mollie\lib\Traits\Jsonable;
use stdClass;

class OrderLine implements \JsonSerializable
{
    use Jsonable;

    public $type;

    public $category;

    public $name;

    public $quantity;

    public $unitPrice;

    public $discountAmount;

    public $totalAmount;

    public $vatRate;

    public $vatAmount;

    public $sku;

    public $imageUrl;

    public $productUrl;

    public $metadata;

    /**
     * @param CartItem|stdClass $oPosition
     * @param Currency          $currency
     * @throws Exception
     * @return OrderLine
     */
    public static function factory($oPosition, Currency $currency): self
    {
        if (!$oPosition) {
            throw new \RuntimeException('$oPosition invalid: ', print_r($oPosition, 1));
        }

        $orderLine = new self();

        $orderLine->type = self::getType($oPosition->nPosTyp);
        // TODO: FktAttr? $orderLine->category

        $orderLine->name = $oPosition->cName;
        if (!$orderLine->name || !is_string($orderLine->name)) {
            $orderLine->name = $oPosition->cArtNr ?: '(null)';
        }

        $_vatRate = (float)$oPosition->fMwSt / 100;
        if ((int)$oPosition->nPosTyp === C_WARENKORBPOS_TYP_KUPON) {
            $_netto   = round($oPosition->fPreis * (1 + $_vatRate), 4);
            $_vatRate = 0;
        } else {
            $_netto = round($oPosition->fPreis, 4);
        }
        $_amount = (float)$oPosition->nAnzahl;

        if (fmod($oPosition->nAnzahl, 1) !== 0.0) {
            $_netto          *= $_amount;
            $_amount          = 1;
            $orderLine->name .= sprintf(' (%.2f %s)', (float)$oPosition->nAnzahl, $oPosition->cEinheit);
        }

        // TODO vorher 2
        $unitPriceNetto = round(($currency->getConversionFactor() * $_netto), 4);
        $unitPrice      = round($unitPriceNetto * (1 + $_vatRate), 2);
        $totalAmount    = round($_amount * $unitPrice, 2);
        $vatAmount      = round($totalAmount - ($totalAmount / (1 + $_vatRate)), 2);

        $orderLine->quantity    = (int)$_amount;
        $orderLine->unitPrice   = new Amount($unitPrice, $currency, false);
        $orderLine->totalAmount = new Amount($totalAmount, $currency, false);
        $orderLine->vatRate     = number_format($_vatRate * 100, 2);
        $orderLine->vatAmount   = new Amount($vatAmount, $currency, false);

        $metadata = [];

        if (isset($oPosition->Artikel)) {
            $orderLine->sku       = $oPosition->Artikel->cArtNr;
            $metadata['kArtikel'] = $oPosition->kArtikel;
            if ($oPosition->cUnique !== '') {
                $metadata['cUnique'] = $oPosition->cUnique;
            }
        }

        if (isset($oPosition->WarenkorbPosEigenschaftArr) && is_array($oPosition->WarenkorbPosEigenschaftArr) && count($oPosition->WarenkorbPosEigenschaftArr)) {
            $metadata['properties'] = [];
            /** @var CartItemProperty $eigenschaft */
            foreach ($oPosition->WarenkorbPosEigenschaftArr as $eigenschaft) {
                $metadata['properties'][] = [
                    'kEigenschaft'     => $eigenschaft->kEigenschaft,
                    'kEigenschaftWert' => $eigenschaft->kEigenschaftWert,
                    'name'             => $eigenschaft->cEigenschaftName,
                    'value'            => $eigenschaft->cEigenschaftWertName,
                ];
                if (strlen(json_encode($metadata)) > 1000) {
                    array_pop($metadata['properties']);

                    break;
                }
            }
        }
        $orderLine->metadata = $metadata;

        return $orderLine;
    }

    /**
     * @param $nPosTyp
     * @throws Exception
     * @return string
     */
    protected static function getType($nPosTyp): string
    {
        switch ($nPosTyp) {
            case C_WARENKORBPOS_TYP_ARTIKEL:
            case C_WARENKORBPOS_TYP_GRATISGESCHENK:
                // TODO: digital / Download Artikel?
                return OrderLineType::TYPE_PHYSICAL;
            case C_WARENKORBPOS_TYP_VERSANDPOS:
                return OrderLineType::TYPE_SHIPPING_FEE;
            case C_WARENKORBPOS_TYP_VERPACKUNG:
            case C_WARENKORBPOS_TYP_VERSANDZUSCHLAG:
            case C_WARENKORBPOS_TYP_ZAHLUNGSART:
            case C_WARENKORBPOS_TYP_VERSAND_ARTIKELABHAENGIG:
            case C_WARENKORBPOS_TYP_NACHNAHMEGEBUEHR:
                return OrderLineType::TYPE_SURCHARGE;
            case C_WARENKORBPOS_TYP_GUTSCHEIN:
            case C_WARENKORBPOS_TYP_KUPON:
            case C_WARENKORBPOS_TYP_NEUKUNDENKUPON:
                return OrderLineType::TYPE_DISCOUNT;
        }

        throw new Exception('Unknown PosTyp.', (int)$nPosTyp);
    }

    /**
     * @param OrderLine[] $orderLines
     * @param Amount      $amount
     * @param Currency    $currency
     * @return null|OrderLine
     */
    public static function getRoundingCompensation(array $orderLines, Amount $amount, Currency $currency): ?self
    {
        $sum = .0;
        foreach ($orderLines as $line) {
            $sum += (float)$line->totalAmount->value;
        }
        if (abs($sum - (float)$amount->value) > 0) {
            $diff = (round((float)$amount->value - $sum, 2));
            if ($diff !== 0.0) {
                $line            = new self();
                $line->type      = $diff > 0 ? OrderLineType::TYPE_SURCHARGE : OrderLineType::TYPE_DISCOUNT;
                $line->name      = 'Rundungsausgleich';
                $line->quantity  = 1;
                $line->unitPrice = new Amount($diff, $currency, false, false);

                $line->totalAmount = new Amount($diff, $currency, false, false);
                $line->vatRate     = '0.00';
                $line->vatAmount   = new Amount(0, $currency, false, false);

                return $line;
            }
        }

        return null;
    }

    /**
     * @param Bestellung $oBestellung
     * @return OrderLine
     */
    public static function getCredit(Bestellung $oBestellung): self
    {
        $line              = new self();
        $line->type        = OrderLineType::TYPE_STORE_CREDIT;
        $line->name        = 'Guthaben';
        $line->quantity    = 1;
        $line->unitPrice   = (object)[
            'value'    => number_format($oBestellung->Waehrung->getConversionFactor() * $oBestellung->fGuthaben, 2, '.', ''),
            'currency' => $oBestellung->Waehrung->getCode(),
        ];
        $line->totalAmount = (object)[
            'value'    => number_format($oBestellung->Waehrung->getConversionFactor() * $oBestellung->fGuthaben, 2, '.', ''),
            'currency' => $oBestellung->Waehrung->getCode(),
        ];
        $line->vatRate     = '0.00';
        $line->vatAmount   = (object)[
            'value'    => number_format(0, 2, '.', ''),
            'currency' => $oBestellung->Waehrung->getCode(),
        ];

        return $line;
    }
}
