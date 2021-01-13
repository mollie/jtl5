<?php


namespace Plugin\ws5_mollie\lib\Model;


use JsonSerializable;
use JTL\Model\DataAttribute;
use JTL\Model\DataModel;
use JTL\Services\JTL\Validation\Rules\DateTime;
use RuntimeException;

/**
 * Class QueueModel
 * @package Plugin\ws5_mollie\lib\Model
 *
 * @property int $id
 * @property string $type
 * @property string $data
 * @property string $result
 * @property DateTime $done
 * @property DateTime $created
 * @property DateTime $modified
 *
 * @method void setId(int $id)
 * @method void setType(string $type)
 * @method void setData(string $data)
 * @method void setResult(string $result)
 * @method void setDone(string $done)
 * @method void setCreated(string $created)
 * @method void setModified(string $modified)
 *
 * @method int getId()
 * @method string getType()
 * @method string getData()
 * @method string getResult()
 * @method string getDone()
 * @method string getCreated()
 * @method string getModified()
 *
 */
class QueueModel extends DataModel implements JsonSerializable
{

    public function setKeyName($keyName): void
    {
        throw new RuntimeException(__METHOD__ . ': setting of keyname is not supported', self::ERR_DATABASE);
    }

    public function getAttributes(): array
    {
        static $attr = null;
        if ($attr === null) {
            $attr = [];
            $attr['id'] = DataAttribute::create('kId', 'int', null, false, true);
            $attr['type'] = DataAttribute::create('cType', 'string', null, false, false);
            $attr['data'] = DataAttribute::create('cData', 'string', null, true, false);
            $attr['result'] = DataAttribute::create('cResult', 'string', null, true, false);
            $attr['done'] = DataAttribute::create('dDone', 'datetime', null, true, false);
            $attr['created'] = DataAttribute::create('dCreated', 'datetime', null, false, false);
            $attr['modified'] = DataAttribute::create('dModified', 'datetime', null, true, false);

        }
        return $attr;
    }

    /**
     * @inheritDoc
     */
    public function getTableName(): string
    {
        return 'xplugin_ws5_mollie_queue';
    }

    public function jsonSerialize()
    {
        return $this->rawArray();
    }
}