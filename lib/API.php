<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib;

use Exception;
use JsonSerializable;
use stdClass;

class API
{
    /**
     * @param stdClass $data
     * @throws Exception
     * @return JsonSerializable
     */
    public static function run(stdClass $data): JsonSerializable
    {
        if (($data->controller ?? false) && ($data->action ?? false)) {
            $controller = ucfirst(strtolower($data->controller ?? ''));
            $action     = strtolower($data->action);

            if (($class = "\\Plugin\\ws5_mollie\\lib\\Controller\\{$controller}Controller") && class_exists($class)) {
                if (method_exists($class, $action)) {
                    return $class::$action($data->data ?? new stdClass());
                }

                throw new \RuntimeException('Controller-Action not found.', 404);
            }

            throw new \RuntimeException('Controller-Class not found.', 404);
        }

        throw new \RuntimeException('Controller or Action missing.', 400);
    }
}
