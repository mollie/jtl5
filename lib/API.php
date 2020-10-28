<?php


namespace Plugin\ws5_mollie\lib;

class API
{

    /**
     * @param \stdClass $data
     * @throws \Exception
     */
    public static function run(\stdClass $data): \JsonSerializable
    {
        if ($data->controller && $data->action) {
            $controller = ucfirst(strtolower($data->controller));
            $action = strtolower($data->action);

            if (($class = "\\Plugin\\ws5_mollie\\lib\\Controller\\{$controller}Controller") && class_exists($class)) {
                if (method_exists($class, $action)) {
                    return $class::$action($data->data ?? new \stdClass());
                }
                throw new \Exception('Controller-Action not found.');
            }
            throw new \Exception('Controller-Class not found.');
        }
        throw new \Exception('Controller or Action missing.');
    }
}