<?php


namespace Plugin\ws5_mollie\lib;


use Composer\CaBundle\CaBundle;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Plugin\ws5_mollie\lib\Traits\Plugin;
use Shop;

class MollieAPI
{
    /**
     * @var MollieApiClient
     */
    protected $client;


    use Plugin;

    /**
     * @var bool
     */
    protected $test;


    public function __construct($test = false)
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
            if (self::Plugin()->getConfig()->getValue("testAsAdmin") === 'on') {
                $_GET['fromAdmin'] = 'yes';
                return Shop::isAdmin(true);
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * @return MollieApiClient
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public function getClient(): MollieApiClient
    {
        if (!$this->client) {
            $this->client = new MollieApiClient(new Client([
                RequestOptions::VERIFY => CaBundle::getBundledCaBundlePath(),
                RequestOptions::TIMEOUT => 60,
            ]));
            $this->client->setApiKey(self::getAPIKey($this->test));
            $this->client->addVersionString("JTL-Shop/" . APPLICATION_VERSION);
            $this->client->addVersionString('ws5_mollie/' . self::Plugin()->getCurrentVersion());
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
            return self::Plugin()->getConfig()->getValue("test_apiKey");
        }
        return self::Plugin()->getConfig()->getValue("apiKey");
    }

    /**
     * @return bool
     */
    public function isTest(): bool
    {
        return $this->test;
    }

}