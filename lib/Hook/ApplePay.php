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

            // Reset CreditCard-Token after Order!
            if (
                ($key = sprintf('kPlugin_%d_creditcard', PluginHelper::getPlugin()->getID()))
                && array_key_exists($key, $_SESSION) && !array_key_exists('Zahlungsart', $_SESSION)
            ) {
                unset($_SESSION[$key]);
            }

            if (!array_key_exists('ws_mollie_applepay_available', $_SESSION)) {
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
}
