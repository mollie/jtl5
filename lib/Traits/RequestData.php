<?php


namespace Plugin\ws5_mollie\lib\Traits;


trait RequestData
{

    protected $requestData;

    /**
     * @param $key string
     * @return mixed|null
     * @deprecated
     */
    public function RequestData(string $key)
    {
        if (!$this->getRequestData()) {
            $this->loadRequest();
        }
        return $this->requestData[$key] ?? null;
    }

    /**
     * @return array|null
     * @deprecated
     */
    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     * @deprecated
     */
    public function setRequestData($key, $value): self
    {
        if (!$this->requestData) {
            $this->requestData = [];
        }
        $this->requestData[$key] = $value;
        return $this;
    }

    abstract public function loadRequest(array &$options = []): self;

    public function jsonSerialize()
    {
        return $this->requestData;
    }

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