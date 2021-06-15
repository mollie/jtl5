<?php

namespace Plugin\ws5_mollie\lib\Model;

use JsonSerializable;
use RuntimeException;
use Shop;
use stdClass;

abstract class AbstractModel implements JsonSerializable
{

    const TABLE = NULL;
    const PRIMARY = NULL;

    const NULL = '_DBNULL_';

    protected $new = false;

    protected $data;

    public function __construct($data = null)
    {
        $this->data = $data;
        if (!$data) {
            $this->new = true;
        }
    }

    /**
     * @param $id
     * @param null $col
     * @param false $failIfNotExists
     * @return static
     */
    public static function fromID($id, $col = self::PRIMARY, $failIfNotExists = false)
    {
        if ($payment = Shop::Container()->getDB()->executeQueryPrepared("SELECT * FROM " . static::TABLE . " WHERE `$col` = :id",
            [':id' => $id], 1)) {
            return new static($payment);
        }
        if ($failIfNotExists) {
            throw new RuntimeException(sprintf('Model %s in %s nicht gefunden!', $id, static::TABLE));
        }
        return new static();
    }

    /**
     * @return mixed|stdClass|null
     */
    public function jsonSerialize()
    {
        return $this->data;
    }

    public function __get($name)
    {
        if (isset($this->data->$name)) {
            return $this->data->$name;
        }
        return null;
    }

    public function __set($name, $value)
    {
        if (!$this->data) {
            $this->data = new stdClass();
        }
        $this->data->$name = $value;
    }

    public function __isset($name)
    {
        return isset($this->data->$name);
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        if (!$this->data) {
            throw new RuntimeException('No Data to save!');
        }

        if ($this->new) {
            Shop::Container()->getDB()->insertRow(static::TABLE, $this->data);
            $this->new = false;
            return true;
        }
        Shop::Container()->getDB()->updateRow(static::TABLE, static::PRIMARY, $this->data->{static::PRIMARY}, $this->data);
        return true;
    }


}