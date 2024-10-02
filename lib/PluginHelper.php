<?php

namespace Plugin\ws5_mollie\lib;

use WS\JTL5\V1_0_16\Helper\AbstractPluginHelper;

/**
 * Provides functionality like get and set settings.
 * Add your own plugin helper functions here if they are not available via AbstractPluginHelper
 */
class PluginHelper extends AbstractPluginHelper
{
    protected static $pluginId = 'ws5_mollie';

    public static function cleanupPaymentLogs(): void
    {
        self::getDB()->executeQueryPrepared('DELETE FROM tzahlungslog WHERE cModulId LIKE :cModulId AND dDatum < DATE_SUB(NOW(), INTERVAL 3 MONTH) LIMIT 100000;',
        [
            'cModulId' => 'kPlugin_' . self::getPlugin()->getID() . '_%'
        ],
            10);
    }
}
