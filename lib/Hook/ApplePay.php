<?php


namespace Plugin\ws5_mollie\lib\Hook;


use JTL\Shop;

class ApplePay extends AbstractHook
{

    public static function execute($args_arr = []): void
    {

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return;
        }

        if (!array_key_exists('ws_mollie_applepay_available', $_SESSION)) {
            Shop::Smarty()->assign('applePayCheckURL', json_encode(self::Plugin()->getPaths()->getBaseURL() . 'applepay.php'));
            pq('body')->append(Shop::Smarty()->fetch(self::Plugin()->getPaths()->getFrontendPath() . 'template/applepay.tpl'));
        }

    }

    public static function isAvailable(): bool
    {
        if (array_key_exists('ws_mollie_applepay_available', $_SESSION)) {
            return $_SESSION['ws_mollie_applepay_available'];
        }
        return false;
    }

    public static function setAvailable(bool $status): void
    {
        $_SESSION['ws_mollie_applepay_available'] = $status;
    }

}