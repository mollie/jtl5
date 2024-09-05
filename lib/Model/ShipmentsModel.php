<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Model;

use JTL\Services\JTL\Validation\Rules\DateTime;
use WS\JTL5\V1_0_16\Model\AbstractModel;

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
    public const TABLE   = 'xplugin_ws5_mollie_shipments';
    public const PRIMARY = 'kId';

    /**
     * @return bool
     */
    public function save(): bool
    {
        $this->dModified = date('Y-m-d H:i:s');

        return parent::save();
    }
}
