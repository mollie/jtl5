<?php


namespace ws5_mollie\Model;


use Exception;
use JTL\Model\DataAttribute;
use JTL\Services\JTL\Validation\Rules\DateTime;

/**
 * Class OrderModel
 * @package ws5_mollie\Model
 *
 * @property int $bestellung
 * @property string $orderId
 * @property string $transactionId
 * @property string $thirdId
 * @property string $status
 * @property string $hash
 * @property bool $test
 * @property bool $synced
 * @property DateTime $modified
 * @property DateTime $created
 *
 * @method void setBestellung(int $bestellung)
 * @method void setOrderId(string $orderId)
 * @method void setTransactionId(string $transactionId)
 * @method void setThirdId(string $thirdId)
 * @method void setStatus(string $status)
 * @method void setHash(string $hash)
 * @method void setTest(bool $test)
 * @method void setSynced(bool $synced)
 * @method void setModified(string $modified)
 * @method void setCreated(string $created)
 *
 * @method int getBestellung()
 * @method string getOrderId()
 * @method string getTransactionId()
 * @method string getThirdId()
 * @method string getStatus()
 * @method string getHash()
 * @method bool getTest()
 * @method bool getSynced()
 * @method string getModified()
 * @method string getCreated()
 *
 */
final class OrderModel extends \JTL\Model\DataModel
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
            $attr['id'] = DataAttribute::create('kId', 'int', null, false, true);
            $attr['bestellung'] = DataAttribute::create('kBestellung', 'int', null, true);
            $attr['orderId'] = DataAttribute::create('cOrderId', 'string', '', false, false);
            $attr['transactionId'] = DataAttribute::create('cTransactionId', 'string', '', false);
            $attr['thirdId'] = DataAttribute::create('cThirdId', 'string', '', false);
            $attr['status'] = DataAttribute::create('cStatus', 'string', '', false);
            $attr['hash'] = DataAttribute::create('cHash', 'string', '', false);
            $attr['test'] = DataAttribute::create('bTest', 'bool', false, false);
            $attr['synced'] = DataAttribute::create('bSynced', 'bool', false, false);
            $attr['modified'] = DataAttribute::create('dModified', 'datetime', date('Y-m-d H:i:s'), false);
            $attr['created'] = DataAttribute::create('dCreated', 'datetime', date('Y-m-d H:i:s'), false);
        }

        return $attr;
    }

    /**
     * @inheritDoc
     */
    public function getTableName(): string
    {
        return 'xplugin_ws5_mollie_orders';
    }
}