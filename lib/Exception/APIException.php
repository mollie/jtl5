<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Exception;

use Throwable;

class APIException extends \RuntimeException
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, 200, $previous);
    }
}
