<?php


namespace Plugin\ws5_mollie\lib\Model;


use JsonSerializable;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Services\JTL\Validation\Rules\DateTime;
use RuntimeException;

/**
 * Class OrderModel
 * @package ws5_mollie\Model
 *
 * @property int $kLieferschein
 * @property int $kBestellung
 * @property string $cOrderId
 * @property string $cShipmentId
 * @property string $cCarrier
 * @property string $cCode
 * @property string $cUrl
 * @property DateTime $dModified
 * @property DateTime $dCreated
 *
 */
final class ShipmentsModel extends AbstractModel
{

    const TABLE = "xplugin_ws5_mollie_shipments";
    const PRIMARY = 'kId';

    public function save(): bool
    {
        $this->dModified = date("Y-m-d H:i:s");
        return parent::save();
    }

}