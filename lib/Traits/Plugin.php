<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Traits;

use JTL\Plugin\Helper;
use JTL\Plugin\PluginInterface;
use RuntimeException;

trait Plugin
{
    /**
     * @var PluginInterface
     */
    protected static $oPlugin;

    /**
     * @return PluginInterface
     */
    public static function Plugin(): PluginInterface
    {
        if (!(self::$oPlugin = Helper::getPluginById('ws5_mollie'))) {
            throw new RuntimeException('Could not load Plugin!');
        }

        return self::$oPlugin;
    }
}
