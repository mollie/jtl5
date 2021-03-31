<?php


namespace Plugin\ws5_mollie\lib\Traits;


trait RequestData
{

    protected $reqestData;

    /**
     * @param $key string
     * @return mixed|null
     */
    public function RequestData(string $key)
    {
        if (!$this->getRequestData()) {
            $this->loadRequest();
        }
        return $this->reqestData[$key] ?? null;
    }

    public function getRequestData(): ?array
    {
        return $this->reqestData;
    }

    abstract public function loadRequest(): self;

    public function setRequestData($key, $value): self
    {
        if (!$this->reqestData) {
            $this->reqestData = [];
        }
        $this->reqestData[$key] = $value;
        return $this;
    }

}