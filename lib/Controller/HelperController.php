<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use JTL\Shop;
use stdClass;
use WS\JTL5\Backend\AbstractResult;
use WS\JTL5\Exception\APIException;

class HelperController extends \WS\JTL5\Backend\Controller\HelperController
{
    public static function selectAll(stdClass $data): AbstractResult
    {
        $response = null;
        if ($data->query ?? false) {
            if (isset($data->query) && is_object($data->params) && property_exists($data->params, ':limit') && property_exists($data->params, ':offset') && strpos($data->query, 'LIMIT') === false) {
                $query          = rtrim($data->query, ';') . ' LIMIT :offset, :limit;';
                $maxItemsParams = (array)clone $data->params;
                unset($maxItemsParams[':offset'], $maxItemsParams[':limit']);
                $response = [
                    'items'    => Shop::Container()->getDB()->executeQueryPrepared($query, (array)($data->params ?? []), 2),
                    'maxItems' => Shop::Container()->getDB()->executeQueryPrepared($data->query, (array)($maxItemsParams ?? []), 3),
                ];
            } else {
                $response = Shop::Container()->getDB()->executeQueryPrepared($data->query, (array)($data->params ?? []), 2);
            }
        }
        if (!is_array($response) && ($error = Shop::Container()->getDB()->getErrorMessage())) {
            throw new APIException('DB Error: ' . $error);
        }

        return new AbstractResult($response);
    }
}
