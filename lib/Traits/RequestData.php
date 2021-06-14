<?php


namespace Plugin\ws5_mollie\lib\Traits;


trait RequestData
{

    protected $requestData;


    /**
     * @return array|null
     * @deprecated
     */
    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    public function jsonSerialize()
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

    public function __get($name)
    {
        if (!$this->requestData) {
            $this->loadRequest();
        }
        return is_string($this->requestData[$name]) ? utf8_decode($this->requestData[$name]) : $this->requestData[$name];
    }

    public function __set($name, $value)
    {
        if (!$this->requestData) {
            $this->requestData = [];
        }

        $this->requestData[$name] = is_string($value) ? utf8_encode($value) : $value;

        return $this;
    }

    public function __serialize(): array
    {
        return $this->requestData ?: [];
    }

    public function __isset($name)
    {
        return $this->requestData[$name] !== null;
    }


}