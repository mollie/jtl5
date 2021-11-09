<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use JTL\Shop;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use stdClass;
use WS\JTL5\Backend\AbstractResult;
use WS\JTL5\Backend\Controller\AbstractController;
use WS\JTL5\Model\ModelInterface;

class QueueController extends AbstractController
{
    /**
     * @param stdClass $data
     * @return AbstractResult
     */
    public static function all(stdClass $data): AbstractResult
    {
        return HelperController::selectAll($data);
    }

    public static function delete(stdClass $data): AbstractResult
    {
        if (isset($data->id) && ($id = (int)$data->id)) {
            return new AbstractResult(Shop::Container()->getDB()->delete('xplugin_ws5_mollie_queue', 'kId', $id));
        }

        return new AbstractResult(false);
    }

    public static function unlock(stdClass $data): AbstractResult
    {
        if (isset($data->id) && ($id = (int)$data->id)) {
            return new AbstractResult(Shop::Container()->getDB()->update('xplugin_ws5_mollie_queue', 'kId', $id, (object)[
                'bLock' => ModelInterface::NULL
            ]));
        }

        return new AbstractResult(false);
    }

    public static function run(stdClass $data): AbstractResult
    {
        if (isset($data->id) && ($id = (int)$data->id)) {
            $todo          = QueueModel::fromID($id, 'kId');
            $todo->cError  = ModelInterface::NULL;
            $todo->dDone   = ModelInterface::NULL;
            $todo->cResult = ModelInterface::NULL;
            $todo->bLock   = ModelInterface::NULL;

            return new AbstractResult($todo->save());
        }

        return new AbstractResult(false);
    }
}
