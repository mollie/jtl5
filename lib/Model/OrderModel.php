<?php


namespace Plugin\ws5_mollie\lib\Model;


use JTL\Services\JTL\Validation\Rules\DateTime;

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

    const TABLE = "xplugin_ws5_mollie_orders";
    const PRIMARY = "kId";
}