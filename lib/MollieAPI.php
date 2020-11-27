<?php


namespace Plugin\ws5_mollie\lib;


use Composer\CaBundle\CaBundle;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use JTL\Plugin\Helper;
use JTL\Plugin\Plugin;
use JTL\Plugin\PluginInterface;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use RuntimeException;
use Shop;

class MollieAPI
{
    /**
     * @var MollieApiClient
     */
    protected static $client;


    /**
     * @var PluginInterface
     */
    protected static $oPlugin;

    /**
     * @var bool
     */
    protected static $test;

    private function __construct()
    {
    }

    /**
     * @param bool $test
     * @return MollieApiClient
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public static function API($test = false): MollieApiClient
    {
        if (self::$client === null) {

            self::$test = $test;

            self::$client = new MollieApiClient(
                new Client([
                    RequestOptions::VERIFY => CaBundle::getBundledCaBundlePath(),
                    RequestOptions::TIMEOUT => 60,
                ])
            );
            self::$client->setApiKey(self::getAPIKey(self::$test));
            self::$client->addVersionString("JTL-Shop/" . APPLICATION_VERSION);
            self::$client->addVersionString('ws5_mollie/' . self::Plugin()->getCurrentVersion());
        }
        return self::$client;
    }

    /**
     * @param boolean $test
     * @return string
     */
    protected static function getAPIKey(bool $test): string
    {
        if ($test) {
            return self::Plugin()->getConfig()->getValue("test_apiKey");
        }
        return self::Plugin()->getConfig()->getValue("apiKey");
    }

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

    /**
     * @return bool
     */
    public static function isTest(): ?bool
    {
        return self::$test;
    }


    /**
     *
     * true = TEST
     * false = LIVE
     *
     * @return bool
     * @throws Exception
     */
    public static function getMode(): bool
    {
        if (self::Plugin()->getConfig()->getValue("testAsAdmin") === 'on') {
            $_GET['fromAdmin'] = 'yes';
            return Shop::isAdmin(true);
        }
        return false;
    }

}