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
 * @property int $lieferschein
 * @property int $bestellung
 * @property string $orderId
 * @property string $shipmentId
 * @property string $carrier
 * @property string $code
 * @property string $url
 * @property DateTime $modified
 * @property DateTime $created
 *
 * @method void setLieferschein(int $lieferschein)
 * @method void setBestellung(int $bestellung)
 * @method void setOrderId(string $orderId)
 * @method void setShipmentId(string $shipmentId)
 * @method void setCarrier(string $carrier)
 * @method void setCode(string $code)
 * @method void setUrl(string $url)
 * @method void setModified(string $modified)
 * @method void setCreated(string $created)
 *
 * @method int getLieferschein()
 * @method int getBestellung()
 * @method string getOrderId()
 * @method string getShipmentId()
 * @method string getCarrier()
 * @method string getCode()
 * @method string getUrl()
 * @method string getModified()
 * @method string getCreated()
 *
 */
final class ShipmentsModel extends DataModel implements JsonSerializable
{

    /**
     * @inheritDoc
     */
    public function setKeyName($keyName): void
    {
        throw new RuntimeException(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    public function save(array $partial = null): bool
    {
        $this->setModified(date("Y-m-d H:i:s"));
        return parent::save($partial);
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        static $attr = null;

        if ($attr === null) {
            $attr = [];
            $attr['lieferschein'] = DataAttribute::create('kLieferschein', 'int', null, false, true);
            $attr['bestellung'] = DataAttribute::create('kBestellung', 'int', null, false);
            $attr['orderId'] = DataAttribute::create('cOrderId', 'string', '', false, false);
            $attr['shipmentId'] = DataAttribute::create('cShipmentId', 'string', '', false, false);

            $attr['carrier'] = DataAttribute::create('cCarrier', 'string', '', false);
            $attr['code'] = DataAttribute::create('cCode', 'string', '', false);
            $attr['url'] = DataAttribute::create('cUrl', 'string', '', false);
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
        return 'xplugin_ws5_mollie_shipments';
    }

    public function jsonSerialize()
    {
        return $this->rawArray();
    }
}