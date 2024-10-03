<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use Exception;
use Plugin\ws5_mollie\lib\PluginHelper;
use WS\JTL5\V1_0_16\Hook\AbstractHook;

class ApplePay extends AbstractHook
{
    /**
     * @param array $args_arr
     * @throws Exception
     */
    public static function execute($args_arr = []): void
    {
        try {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                return;
            }

            // append applepay script
            if (!array_key_exists('ws_mollie_applepay_available', $_SESSION) && self::isActive()) {
                pq('head')->append("<script>window.MOLLIE_APPLEPAY_CHECK_URL = '" . PluginHelper::getPlugin()->getPaths()->getBaseURL() . "applepay.php';</script>");
            }
        } catch (Exception $e) {
        }
    }

    /**
     * @return bool
     */
    public static function isAvailable(): bool
    {
        if (array_key_exists('ws_mollie_applepay_available', $_SESSION)) {
            return $_SESSION['ws_mollie_applepay_available'];
        }

        return false;
    }

    /**
     * @param bool $status
     */
    public static function setAvailable(bool $status): void
    {
        $_SESSION['ws_mollie_applepay_available'] = $status;
    }

    /**
     * @return bool
     */
    public static function isActive(): bool
    {
        $kZahlunsgart = PluginHelper::getDB()->executeQueryPrepared('SELECT kZahlungsart FROM tzahlungsart WHERE cModulId = :cModulId',
            [
                ':cModulId' => 'kPlugin_' . PluginHelper::getPlugin()->getID() . '_applepay'
            ], 1)->kZahlungsart ?? null;
        if ($kZahlunsgart > 0) {
            return PluginHelper::getDB()->executeQueryPrepared('SELECT * FROM tversandartzahlungsart WHERE kZahlungsart = :kZahlungsart',
                [
                    ':kZahlungsart' => $kZahlunsgart
                ], 3) > 0;
        } else {
            return false;
        }
    }
}
