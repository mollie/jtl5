<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Traits;

/**
 * Trait Jsonable
 * @package Plugin\ws5_mollie\lib\Traits
 */
trait Jsonable
{
    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * @return string[] (null|string)[]
     *
     * @psalm-return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), static function ($value) {
            return !($value === null || (is_string($value) && $value === ''));
        });
    }
}
