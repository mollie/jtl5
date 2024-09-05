<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Model;

use Exception;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Shop;
use Plugin\ws5_mollie\lib\PluginHelper;
use WS\JTL5\V1_0_16\Model\AbstractModel;

/**
 * Class QueueModel
 * @package Plugin\ws5_mollie\lib\Model
 *
 * @property int $kId
 * @property string $cType
 * @property string $cData
 * @property string $cResult
 * @property string $dDone
 * @property string $cError
 * @property string $dCreated
 * @property string $dModified
 * @property bool $bLock
 *
 */
class QueueModel extends AbstractModel
{
    public const TABLE   = 'xplugin_ws5_mollie_queue';
    public const PRIMARY = 'kId';

    /**
     * @return array|int|object
     */
    public static function cleanUp()
    {
        ifndef('MOLLIE_CLEANUP_DAYS', 30);
        /** @noinspection PhpUndefinedConstantInspection */
        return PluginHelper::getDB()->executeQueryPrepared(sprintf('DELETE FROM %s WHERE dDone IS NOT NULL AND (bLock IS NULL OR bLock = "0000-00-00 00:00:00") AND dCreated < DATE_SUB(NOW(), INTERVAL %d DAY)', self::TABLE, (int)MOLLIE_CLEANUP_DAYS), [], 3);
    }

    /**
     * @param null|string $result
     * @param null|string $date
     * @return bool
     */
    public function done(string $result = null, string $date = null): bool
    {
        $this->cResult = $result       ?? self::NULL;
        $this->cError  = $this->cError ?? self::NULL;
        $this->dDone   = $date         ?? date('Y-m-d H:i:s');
        $this->bLock   = self::NULL;

        return $this->save();
    }

    /**
     * @return true
     */
    public function save(): bool
    {
        $this->dModified = date('Y-m-d H:i:s');

        return parent::save();
    }

    /**
     * @param string $hook
     * @param array  $args_arr
     * @param string $type
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     * @return bool
     */
    public static function saveToQueue(string $hook, array $args_arr, string $type = 'hook'): bool
    {
        $mQueue           = new self();
        $mQueue->cType    = $type . ':' . $hook;
        $mQueue->cData    = serialize($args_arr);
        $mQueue->dCreated = date('Y-m-d H:i:s');

        try {
            return $mQueue->save();
        } catch (Exception $e) {
            Shop::Container()->getLogService()
                ->error('mollie::saveToQueue: ' . $e->getMessage() . ' - ' . print_r($args_arr, 1));

            return false;
        }
    }
}
