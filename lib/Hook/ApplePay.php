<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use Exception;
use JTL\Shop;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Plugin\ws5_mollie\lib\PluginHelper;
use Psr\Http\Message\ResponseInterface;
use WS\JTL5\V2_0_7\Hook\AbstractHook;

class ApplePay extends AbstractHook
{
    /** @var bool|null Cached for the current request (HOOK_SMARTY_OUTPUTFILTER may run many times) */
    private static ?bool $isActiveCache = null;

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
            if (self::isActive()) {
                $applePayUrl = PluginHelper::getPlugin()->getPaths()->getBaseURL() . 'applepay';
                if (is_string($applePayUrl) && $applePayUrl !== '') {
                    Shop::Smarty()
                        ->assign('wsMollieApplePayUrl', PluginHelper::getPlugin()->getPaths()->getBaseURL() . 'applepay')
                        ->assign('currentApplePayStatus', (isset($_SESSION['ws_mollie_applepay_available']) && $_SESSION['ws_mollie_applepay_available']) ? 1 : 0);
                    pq('head')->append(Shop::Smarty()->fetch(PluginHelper::getPlugin()->getPaths()->getFrontendPath() . 'template/applepay.tpl', false));
                }
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
        if (self::$isActiveCache !== null) {
            return self::$isActiveCache;
        }

        $kZahlunsgart = PluginHelper::getDB()->executeQueryPrepared('SELECT kZahlungsart FROM tzahlungsart WHERE cModulId = :cModulId',
            [
                ':cModulId' => 'kPlugin_' . PluginHelper::getPlugin()->getID() . '_applepay'
            ], 1)->kZahlungsart ?? null;
        if ($kZahlunsgart > 0) {
            self::$isActiveCache = PluginHelper::getDB()->executeQueryPrepared('SELECT * FROM tversandartzahlungsart WHERE kZahlungsart = :kZahlungsart',
                [
                    ':kZahlungsart' => $kZahlunsgart
                ], 3) > 0;
        } else {
            self::$isActiveCache = false;
        }

        return self::$isActiveCache;
    }

    public static function check(ServerRequest $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        if (array_key_exists('available', $data)) {
            self::setAvailable((bool)$data['available']);
        }
        return new Response\TextResponse('OK', 200);
    }
}
