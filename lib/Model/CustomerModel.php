<?php


namespace Plugin\ws5_mollie\lib\Model;


use Exception;
use JTL\Model\DataAttribute;

/**
 * Class CustomerModel
 * @package ws5_mollie\Model
 *
 * @property int $kunde
 * @property string $customerId
 *
 * @method void setKunde(int $kunde)
 * @method void setCustomerId(string $customerId)
 *
 * @method int getKunde()
 * @method string getCustomerId()
 *
 */
final class CustomerModel extends \JTL\Model\DataModel
{

    /**
     * @inheritDoc
     */
    public function setKeyName($keyName): void
    {
        throw new Exception(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        static $attr = null;

        if ($attr === null) {
            $attr = [];
            $attr['kunde'] = DataAttribute::create('kKunde', 'int', null, false, true);
            $attr['customerId'] = DataAttribute::create('customerId', 'string', null, false);
        }

        return $attr;
    }

    /**
     * @inheritDoc
     */
    public function getTableName(): string
    {
        return 'xplugin_ws5_mollie_kunde';
    }
}