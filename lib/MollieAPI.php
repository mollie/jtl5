<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib;

use Composer\CaBundle\CaBundle;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use JTL\Shop;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use WS\JTL5\V1_0_16\Traits\Plugins;

class MollieAPI
{
    use Plugins;

    /**
     * @var MollieApiClient
     */
    protected $client;

    /**
     * @var bool
     */
    protected $test;

    /**
     * @param false $test
     */
    public function __construct(bool $test = false)
    {
        $this->test = $test;
    }

    /**
     *
     * true = TEST
     * false = LIVE
     *
     * @return bool
     */
    public static function getMode(): bool
    {
        try {
            if (PluginHelper::getSetting('testAsAdmin') && PluginHelper::getSetting('test_apiKey') !== '') {
                $_GET['fromAdmin'] = 'yes';

                return Shop::isAdmin(true);
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * @throws ApiException
     * @throws IncompatiblePlatform
     * @return MollieApiClient
     */
    public function getClient(): MollieApiClient
    {
        if (!$this->client) {
            $this->client = new MollieApiClient(new Client([
                RequestOptions::VERIFY  => CaBundle::getBundledCaBundlePath(),
                RequestOptions::TIMEOUT => 60,
            ]));
            $this->client->setApiKey(self::getAPIKey($this->test));
            $this->client->addVersionString('JTL-Shop/' . APPLICATION_VERSION);
            $this->client->addVersionString('ws5_mollie/' . PluginHelper::getPlugin()->getCurrentVersion());
        }

        return $this->client;
    }

    /**
     * @param boolean $test
     * @return string
     */
    protected static function getAPIKey(bool $test): string
    {
        if ($test) {
            return PluginHelper::getSetting('test_apiKey');
        }

        return PluginHelper::getSetting('apiKey');
    }

    /**
     * @return bool
     */
    public function isTest(): bool
    {
        return $this->test;
    }
}
