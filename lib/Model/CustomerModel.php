<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Model;

use WS\JTL5\Model\AbstractModel;

/**
 * Class CustomerModel
 * @package ws5_mollie\Model
 *
 * @property int $kKunde
 * @property string $customerId
 *
 */
final class CustomerModel extends AbstractModel
{
    public const TABLE   = 'xplugin_ws5_mollie_kunde';
    public const PRIMARY = 'kKunde';
}
