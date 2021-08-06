<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\paymentmethod;

use Exception;
use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\ws5_mollie\lib\MollieAPI;
use Plugin\ws5_mollie\lib\Payment\Address;
use Plugin\ws5_mollie\lib\PaymentMethod;
use Session;

require_once __DIR__ . '/../vendor/autoload.php';

class CreditCard extends PaymentMethod
{
    public const CACHE_PREFIX          = 'creditcard';
    public const CACHE_TOKEN           = self::CACHE_PREFIX . ':token';
    public const CACHE_TOKEN_TIMESTAMP = self::CACHE_TOKEN . ':timestamp';

    public const METHOD = \Mollie\Api\Types\PaymentMethod::CREDITCARD;

    public function handleNotification(Bestellung $order, string $hash, array $args): void
    {
        parent::handleNotification($order, $hash, $args);
        $this->clearToken();
    }

    protected function clearToken(): bool
    {
        $this->unsetCache(self::CACHE_TOKEN)
            ->unsetCache(self::CACHE_TOKEN_TIMESTAMP);

        return true;
    }

    public function handleAdditional(array $post): bool
    {
        $components = self::Plugin('ws5_mollie')->getConfig()->getValue($this->moduleID . '_components');
        $profileId  = self::Plugin('ws5_mollie')->getConfig()->getValue('profileId');

        if ($components === 'N' || !$profileId || trim($profileId) === '') {
            return parent::handleAdditional($post);
        }

        $cleared = false;
        if (array_key_exists('clear', $post) && (int)$post['clear']) {
            $cleared = $this->clearToken();
        }

        if ($components === 'S' && array_key_exists('skip', $post) && (int)$post['skip']) {
            return parent::handleAdditional($post);
        }

        try {
            $trustBadge   = (bool)self::Plugin('ws5_mollie')->getConfig()->getValue($this->moduleID . '_trustBadge');
            $locale       = self::getLocale(Session::getInstance()->getLanguage()->getIso(), Session::getCustomer()->cLand ?? null);
            $mode         = MollieAPI::getMode();
            $errorMessage = json_encode(self::Plugin('ws5_mollie')->getLocalization()->getTranslation('mcErrorMessage'), JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            Shop::Container()->getLogService()->error($e->getMessage(), ['e' => $e]);

            return parent::handleAdditional($post);
        }

        if (!$cleared && array_key_exists('cardToken', $post) && ($token = trim($post['cardToken']))) {
            return $this->setToken($token) && parent::handleAdditional($post);
        }

        if (($ctTS = (int)$this->getCache(self::CACHE_TOKEN_TIMESTAMP)) && $ctTS > time()) {
            $token = $this->getCache(self::CACHE_TOKEN);
        }

        Shop::Smarty()->assign('profileId', $profileId)
            ->assign('trustBadge', $trustBadge ?? false)
            ->assign('components', $components)
            ->assign('locale', $locale ?? 'de_DE')
            ->assign('token', $token ?? false)
            ->assign('testMode', $mode ?? false)
            ->assign('errorMessage', $errorMessage ?? 'Unexpected Error.')
            ->assign('mollieLang', self::Plugin('ws5_mollie')->getLocalization()->getTranslations());

        return false;
    }

    protected function setToken(string $token): bool
    {
        $this->addCache(self::CACHE_TOKEN, $token)
            ->addCache(self::CACHE_TOKEN_TIMESTAMP, time() + 3600);

        return true;
    }

    public function getPaymentOptions(Bestellung $order, $apiType): array
    {
        $paymentOptions = [];

        if ($apiType === 'payment') {
            if ($order->Lieferadresse !== null) {
                if (!$order->Lieferadresse->cMail) {
                    $order->Lieferadresse->cMail = $order->oRechnungsadresse->cMail;
                }
                $paymentOptions['shippingAddress'] = new Address($order->Lieferadresse);
            }

            $paymentOptions['billingAddress'] = new Address($order->oRechnungsadresse);
        }
        if ((int)$this->getCache(self::CACHE_TOKEN_TIMESTAMP) > time() && ($token = trim($this->getCache(self::CACHE_TOKEN)))) {
            $paymentOptions['cardToken'] = $token;
        }

        return $paymentOptions;
    }
}
