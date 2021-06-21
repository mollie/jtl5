<?php


namespace Plugin\ws5_mollie\lib\Model;


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

    public const TABLE = "xplugin_ws5_mollie_kunde";
    public const PRIMARY = 'kKunde';

}