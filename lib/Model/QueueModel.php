<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Model;

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
class QueueModel extends AbstractModel
{
    public const TABLE   = 'xplugin_ws5_mollie_queue';
    public const PRIMARY = 'kId';

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
}
