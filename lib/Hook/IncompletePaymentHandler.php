<?php

/**
 * @copyright 2022 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use Exception;
use JTL\Alert\Alert;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Helpers\Request;
use JTL\Shop;
use Plugin\ws5_mollie\lib\PluginHelper;
use WS\JTL5\V1_0_16\Hook\AbstractHook;

class IncompletePaymentHandler extends AbstractHook
{
    const MOLLIE_PAYMENT_NOT_COMPLETED_STRING = 'mollie_payment_not_completed';

    /**
     * @return void
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public static function checkForIncompletePayment(): void
    {
        try {
            // Only check if Mollie payment method is used
            if (!(isset($_SESSION["Zahlungsart"]->cAnbieter) && $_SESSION["Zahlungsart"]->cAnbieter === "Mollie") || Request::isAjaxRequest()) {
                return;
            }

            // Check if coming back from Mollie and payment was not successful. Redirect to check out with "mollie_payment_not_completed"-parameter to be able to show correct error alert in frontend
            if (Shop::getPageType() === PAGE_BESTELLVORGANG && array_key_exists('fillOut', $_REQUEST) && $_REQUEST['fillOut'] === '-1') {

                $queryArray = array_merge($_REQUEST, [static::MOLLIE_PAYMENT_NOT_COMPLETED_STRING => 1]);
                unset($queryArray['fillOut']);

                $queryString = http_build_query($queryArray);
                $checkoutURL = Shop::Container()->getLinkService()->getSpecialPage(LINKTYP_BESTELLVORGANG)->getURL();

                header('Location: ' . $checkoutURL . '?' . $queryString);
            }

            // Add error alert to frontend
            if (array_key_exists(static::MOLLIE_PAYMENT_NOT_COMPLETED_STRING, $_REQUEST) && $_REQUEST[static::MOLLIE_PAYMENT_NOT_COMPLETED_STRING] === '1') {

                $translatedErrorMessage = PluginHelper::getPlugin()->getLocalization()->getTranslation('paymentNotCompleted');
                Shop::Container()->getAlertService()->addAlert(
                    Alert::TYPE_ERROR,
                    $translatedErrorMessage,
                    'mollie_payment_incomplete'
                );
            }

        } catch (Exception $e) {
            Shop::Container()->getLogService()->critical($e->getMessage());
        }
    }
}
