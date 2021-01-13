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
 * @property int $bestellung
 * @property string $orderId
 * @property string $transactionId
 * @property string $thirdId
 * @property string $status
 * @property string $hash
 * @property float $amount
 * @property float $amountRefunded
 * @property string $method
 * @property string $curreny
 * @property string $locale
 * @property bool $test
 * @property bool $synced
 * @property DateTime $modified
 * @property DateTime $created
 *
 * @method void setBestellung(int $bestellung)
 * @method void setOrderId(string $orderId)
 * @method void setBestellNr(string $bestellNr)
 * @method void setTransactionId(string $transactionId)
 * @method void setThirdId(string $thirdId)
 * @method void setStatus(string $status)
 * @method void setHash(string $hash)
 * @method void setAmount(float $amount)
 * @method void setAmountRefunded(float $amountRefunded)
 * @method void setMethod(string $method)
 * @method void setCurrency(string $currency)
 * @method void setLocale(string $locale)
 * @method void setTest(bool $test)
 * @method void setSynced(bool $synced)
 * @method void setModified(string $modified)
 * @method void setCreated(string $created)
 *
 * @method int getBestellung()
 * @method string getOrderId()
 * @method string getBestellNr()
 * @method string getTransactionId()
 * @method string getThirdId()
 * @method string getStatus()
 * @method string getHash()
 * @method float getAmount()
 * @method float getAmountRefunded()
 * @method string getMethod()
 * @method string getCurrency()
 * @method string getLocale()
 * @method bool getTest()
 * @method bool getSynced()
 * @method string getModified()
 * @method string getCreated()
 *
 */
final class OrderModel extends DataModel implements JsonSerializable
{

    /**
     * @inheritDoc
     */
    public function setKeyName($keyName): void
    {
        throw new RuntimeException(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
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
            $attr['bestellNr'] = DataAttribute::create('cBestellNr', 'string', '', false, false);
            $attr['transactionId'] = DataAttribute::create('cTransactionId', 'string', '', false);
            $attr['thirdId'] = DataAttribute::create('cThirdId', 'string', '', false);
            $attr['status'] = DataAttribute::create('cStatus', 'string', '', false);
            $attr['hash'] = DataAttribute::create('cHash', 'string', '', false);

            $attr['amount'] = DataAttribute::create('fAmount', 'float', '', false);
            $attr['amountRefunded'] = DataAttribute::create('fAmountRefunded', 'float', 0.0, false);
            $attr['method'] = DataAttribute::create('cMethod', 'string', '', false);
            $attr['locale'] = DataAttribute::create('cLocale', 'string', '', false);
            $attr['currency'] = DataAttribute::create('cCurrency', 'string', '', false);

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

    public function jsonSerialize()
    {
        return $this->rawArray();
    }
}