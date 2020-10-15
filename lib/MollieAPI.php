<?php


namespace ws5_mollie;


use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use JTL\Plugin\Helper;
use JTL\Plugin\PluginInterface;
use Mollie\Api\MollieApiClient;

class MollieAPI
{
    /**
     * @var MollieApiClient
     */
    protected static $client;

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
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public static function API($test = false): MollieApiClient
    {
        if (self::$client === null) {

            self::$test = $test;

            if (!($oPlugin = Helper::getPluginById(__NAMESPACE__))) {
                throw new \RuntimeException('Could not load Plugin!');
            }


            self::$client = new MollieApiClient(
                new Client([
                    RequestOptions::VERIFY => CaBundle::getBundledCaBundlePath(),
                    RequestOptions::TIMEOUT => 60,
                ])
            );
            self::$client->setApiKey(self::getAPIKey(self::$test, $oPlugin));
            self::$client->addVersionString("JTL-Shop/" . APPLICATION_VERSION);
            self::$client->addVersionString('ws5_mollie/' . $oPlugin->getCurrentVersion());
        }
        return self::$client;
    }

    /**
     * @param $test
     * @param PluginInterface|null $oPlugin
     * @return string
     */
    protected static function getAPIKey($test, PluginInterface $oPlugin = null): string
    {
        if ($oPlugin === null && !($oPlugin = Helper::getPluginById(__NAMESPACE__))) {
            throw new \RuntimeException('Could not load Plugin!');
        }
        if ($test) {
            return $oPlugin->getConfig()->getValue("test_apiKey");
        }
        return $oPlugin->getConfig()->getValue("apiKey");
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
     * @throws \Exception
     */
    public static function getMode(): bool
    {
        return \Shop::isAdmin();
    }

}