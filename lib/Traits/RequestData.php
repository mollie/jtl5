<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Traits;

use Exception;

trait RequestData
{
    /**
     * @var array
     */
    protected $requestData;

    /**
     * @throws Exception
     * @return array
     */
    public function jsonSerialize(): array
    {
        if (!$this->requestData) {
            $this->loadRequest();
        }

        return $this->requestData;
    }

    /**
     * @param array $options
     * @return $this
     */
    abstract public function loadRequest(array &$options = []);

    /**
     * @param string $name
     * @throws Exception
     * @return false|mixed|string
     */
    public function __get(string $name)
    {
        if (!$this->requestData) {
            $this->loadRequest();
        }

        if (!array_key_exists($name, $this->requestData)) {
            return null;
        }

        return is_string($this->requestData[$name]) ? mb_convert_encoding($this->requestData[$name], 'UTF-8', mb_list_encodings()) : $this->requestData[$name];
    }

    /**
     * @param string $name
     * @param mixed  $value
     * @return $this
     */
    public function __set(string $name, $value)
    {
        if (!$this->requestData) {
            $this->requestData = [];
        }

        $this->requestData[$name] = is_string($value) ? mb_convert_encoding($value, 'UTF-8', mb_list_encodings()) : $value;

        return $this;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return $this->requestData ?: [];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name)
    {
        return $this->requestData[$name] !== null;
    }
}
