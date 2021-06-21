<?php


namespace Plugin\ws5_mollie\lib\Model;


/**
 * Class OrderModel
 * @package ws5_mollie\Model
 *
 * @property int $kId
 * @property int|null $kBestellung
 * @property string $cOrderId
 * @property string $cTransactionId
 * @property string $cThirdId
 * @property string $cStatus
 * @property string $cHash
 * @property float $fAmount
 * @property float $fAmountRefunded
 * @property string $cMethod
 * @property string $cCurrency
 * @property string $cLocale
 * @property bool $bTest
 * @property bool $bSynced
 * @property string $dModified
 * @property string $dCreated
 * @property string $cBestellNr;
 * @property string $dReminder
 *
 */
final class OrderModel extends AbstractModel
{

    public const TABLE = "xplugin_ws5_mollie_orders";
    public const PRIMARY = "kId";

    public function save(): bool
    {
        if (!$this->dCreated || $this->dCreated === '0000-00-00 00:00:00') {
            $this->dCreated = date("Y-m-d H:i:s");
        }
        $this->dModified = date("Y-m-d H:i:s");
        return parent::save();
    }

}