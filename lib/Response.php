<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib;

class Response implements \JsonSerializable
{
    protected $data = [];

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function __get($name)
    {
        return $this->data[$name];
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function jsonSerialize()
    {
        if (is_array($this->data)) {
            return $this->data;
        }

        return (object)$this->data;
    }
}
