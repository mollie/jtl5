<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\PluginHelper;
use stdClass;
use WS\JTL5\V1_0_16\Backend\AbstractResult;
use WS\JTL5\V1_0_16\Backend\Controller\AbstractController;
use WS\JTL5\V1_0_16\Model\ModelInterface;

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
            return new AbstractResult(PluginHelper::getDB()->delete('xplugin_ws5_mollie_queue', 'kId', $id));
        }

        return new AbstractResult(false);
    }

    public static function unlock(stdClass $data): AbstractResult
    {
        if (isset($data->id) && ($id = (int)$data->id)) {
            return new AbstractResult(PluginHelper::getDB()->update('xplugin_ws5_mollie_queue', 'kId', $id, (object)[
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
