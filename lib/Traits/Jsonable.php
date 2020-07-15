<?php


namespace ws5_mollie\Traits;


trait Jsonable
{
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), function ($value) {
            return $value === null || (is_string($value) && $value === '') ? false : true;
        });
    }
}