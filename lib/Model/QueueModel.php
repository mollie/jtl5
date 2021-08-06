<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Model;

use Exception;
use JTL\Shop;

/**
 * Class QueueModel
 * @package Plugin\ws5_mollie\lib\Model
 *
 * @property int $kId
 * @property string $cType
 * @property string $cData
 * @property string $cResult
 * @property string $dDone
 * @property string $dCreated
 * @property string $dModified
 * @property bool $bLock
 *
 */
class QueueModel extends \WS\JTL5\Model\AbstractModel
{
    public const TABLE   = 'xplugin_ws5_mollie_queue';
    public const PRIMARY = 'kId';

    /**
     * @return array|int|object
     */
    public static function cleanUp()
    {
        // TODO: DOKU
        ifndef('MOLLIE_CLEANUP_DAYS', 30);
        /** @noinspection PhpUndefinedConstantInspection */
        return Shop::Container()->getDB()->executeQuery(sprintf('DELETE FROM %s WHERE dDone IS NOT NULL AND (bLock IS NULL OR bLock = "0000-00-00 00:00:00") AND dCreated < DATE_SUB(NOW(), INTERVAL %d DAY)', self::TABLE, MOLLIE_CLEANUP_DAYS), 3);
    }

    /**
     * @param string      $result
     * @param null|string $date
     * @return bool
     */
    public function done(string $result, string $date = null): bool
    {
        $this->cResult = $result;
        $this->dDone   = $date ?? date('Y-m-d H:i:s');
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
