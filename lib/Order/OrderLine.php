<?php


namespace ws5_mollie\Order;


use JTL\Cart\CartItem;
use Mollie\Api\Types\OrderLineType;
use ws5_mollie\Traits\Jsonable;

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
     * @param CartItem|\stdClass $oPosition
     * @return OrderLine
     * @throws \Exception
     */
    public static function factory($oPosition, \Currency $currency): OrderLine
    {

        $orderLine = new self();

        $orderLine->type = self::getType($oPosition->nPosTyp);
        // @todo FktAttr? $orderLine->category
        $orderLine->name = $oPosition->cName;

        $_netto = round($oPosition->fPreis, 2);
        $_vatRate = (float)$oPosition->fMwSt / 100;
        $_amount = (float)$oPosition->nAnzahl;

        if (fmod($oPosition->nAnzahl, 1) !== 0.0) {
            $_netto *= $_amount;
            $_amount = 1;
            $orderLine->name .= sprintf(" (%.2f %s)", (float)$oPosition->nAnzahl, $oPosition->cEinheit);
        }

        $unitPriceNetto = round(($currency->getConversionFactor() * $_netto), 2);
        $unitPrice = round($unitPriceNetto * (1 + $_vatRate), 2);
        $totalAmount = round($_amount * $unitPrice, 2);
        $vatAmount = round($totalAmount - ($totalAmount / (1 + $_vatRate)), 2);

        $orderLine->quantity = (int)$_amount;
        $orderLine->unitPrice = new Amount($unitPrice, $currency, false);
        $orderLine->totalAmount = new Amount($totalAmount, $currency, false);
        $orderLine->vatRate = (string)$oPosition->fMwSt;
        $orderLine->vatAmount = new Amount($vatAmount, $currency, false);

        return $orderLine;
    }

    /**
     * @param $nPosTyp
     * @return string
     * @throws \Exception
     */
    protected static function getType($nPosTyp): string
    {
        switch ($nPosTyp) {
            case C_WARENKORBPOS_TYP_ARTIKEL:
            case C_WARENKORBPOS_TYP_GRATISGESCHENK:
                // @todo: digital / Download Artikel?
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

        throw new \Exception('Unknown PosTyp.', (int)$nPosTyp);
    }

    /**
     * @param OrderLine[] $orderlines
     */
    public static function getRoundingCompensation(array $orderlines, Amount $amount, \Currency $currency)
    {
        $sum = .0;
        foreach ($orderlines as $line) {
            $sum += (float)$line->totalAmount->value;
        }
        if (abs($sum - (float)$amount->value) > 0) {
            $diff = (round((float)$amount->value - $sum, 2));
            if ($diff !== 0.0) {
                $line = new self();
                $line->type = $diff > 0 ? OrderLineType::TYPE_SURCHARGE : OrderLineType::TYPE_DISCOUNT;
                $line->name = 'Rundungsausgleich';
                $line->quantity = 1;
                $line->unitPrice = new Amount($diff, $currency, false, false);

                $line->totalAmount = new Amount($diff, $currency, false, false);
                $line->vatRate = "0.00";
                $line->vatAmount = new Amount(0, $currency, false, false);
                return $line;
            }
        }
        return null;
    }

    /**
     * @param \Bestellung $oBestellung
     * @return OrderLine
     */
    public static function getCredit(\Bestellung $oBestellung): OrderLine
    {
        $line = new self();
        $line->type = OrderLineType::TYPE_STORE_CREDIT;
        $line->name = 'Guthaben';
        $line->quantity = 1;
        $line->unitPrice = (object)[
            'value' => number_format($oBestellung->Waehrung->getConversionFactor() * $oBestellung->fGuthaben, 2, '.', ''),
            'currency' => $oBestellung->Waehrung->getCode(),
        ];
        $line->totalAmount = (object)[
            'value' => number_format($oBestellung->Waehrung->getConversionFactor() * $oBestellung->fGuthaben, 2, '.', ''),
            'currency' => $oBestellung->Waehrung->getCode(),
        ];
        $line->vatRate = "0.00";
        $line->vatAmount = (object)[
            'value' => number_format(0, 2, '.', ''),
            'currency' => $oBestellung->Waehrung->getCode(),
        ];
        return $line;
    }

}