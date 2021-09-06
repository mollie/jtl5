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
     * @return array
     * @throws Exception
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
     * @return false|mixed|string
     * @throws Exception
     */
    public function __get(string $name)
    {
        if (!$this->requestData) {
            $this->loadRequest();
        }

        return is_string($this->requestData[$name]) ? utf8_decode($this->requestData[$name]) : $this->requestData[$name];
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

        $this->requestData[$name] = is_string($value) ? utf8_encode($value) : $value;

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
