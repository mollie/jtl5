<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Session\Frontend;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Helper\UrlHelper;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\PluginHelper;
use Plugin\ws5_mollie\lib\Queue;

try {
    global $oPlugin;

    if (Request::isAjaxRequest()) {
        return;
    }

    require_once __DIR__ . '/../../vendor/autoload.php';

    // Run queue synchronous if setting = sync
    if (PluginHelper::getSetting('queue') === 'sync') {
        ifndef('MOLLIE_QUEUE_MAX', 3);
        /** @noinspection PhpUndefinedConstantInspection */
        Queue::runSynchronous(MOLLIE_QUEUE_MAX);
    }

    // eigentlich nicht notwendig, aber naja
    ifndef('LINNKTYPE_BESTELLABSCHLUSS', 33);
    ifndef('LINKTYP_BESTELLSTATUS', 38);
    $linktype_ordercompleted = (int) LINKTYP_BESTELLABSCHLUSS;
    $linktype_orderstatus = (int) LINKTYP_BESTELLSTATUS;

    if (array_key_exists('hash', $_REQUEST) && (UrlHelper::urlHasSpecialPageLinkType($linktype_ordercompleted) || UrlHelper::urlHasSpecialPageLinkType($linktype_orderstatus))) {
        // Use session_write_close to "free" session for finalizeOrder() and make it possible to finalize order simultaneously and wait here for a second before redirecting
        session_write_close();

        // When customer comes back from processPaymentPage and order was cancelled or something went wrong: redirect to checkout with incomplete payment alert
        if (array_key_exists('mollie_payment_error', $_REQUEST) && $_REQUEST['mollie_payment_error'] === '1') {
            Shop::Container()->getLogService()->debug("Mollie - Order was canceled. Redirect to Error-Page: " . $_REQUEST['hash']);
            $url = Shop::Container()->getLinkService()->getSpecialPage(LINKTYP_BESTELLVORGANG)->getURL();
            header('Location: ' . $url . '?fillOut=-1&mollie_payment_not_completed=1');
            exit;
        }

        // In first Redirect from mollie: wait for 1 second before checking the payment status
        if (!array_key_exists('mollie_payment_finalized', $_REQUEST)) {
            Shop::Container()->getLogService()->debug("Mollie - Redirect called from mollie for order: " . $_REQUEST['hash']);
            sleep(1);
            Shop::Container()->getLogService()->debug("Mollie - Waited for 1 second");
        } else {
            Shop::Container()->getLogService()->debug("Mollie - Redirect called from loading page for order: " . $_REQUEST['hash']);
        }

        $sessionHash = trim(Text::htmlentities(Text::filterXSS($_REQUEST['hash'])), '_');
        $paymentSession = PluginHelper::getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash);

        // Check if order is already finalized
        if ($paymentSession && $paymentSession->kBestellung) {

            // If order is finalized: redirect to bestellabschluss/bestellstatus according to shop setting
            $oBestellung = new \JTL\Checkout\Bestellung($paymentSession->kBestellung);
            if (intval($oBestellung->cStatus) !== \BESTELLUNG_STATUS_BEZAHLT) {
                // If order is not marked as paid yet: redirect to processPaymentPage
                Shop::Container()->getLogService()->debug("Mollie - Order finalized but not marked as paid yet -> redirect to loading screen");
                header("Location: " . Shop::getURL() . '/' . PluginHelper::getPlugin()->getPluginID() . '/processPayment?hash=' . $sessionHash);
                exit();
            }

            Shop::Container()->getLogService()->debug("Mollie - Order finalized and marked as paid -> redirect to bestellabschluss");

            if (
                \JTL\Shopsetting::getInstance()
                    ->getValue(CONF_KAUFABWICKLUNG, 'bestellabschluss_abschlussseite') === 'A'
            ) {
                $oBestellID = PluginHelper::getDB()->select('tbestellid', 'kBestellung', $paymentSession->kBestellung);
                if ($oBestellID) {
                    $url = Shop::Container()->getLinkService()->getSpecialPage($linktype_ordercompleted)->getURL();
                    header('Location: ' . $url . '?i=' . $oBestellID->cId);
                    exit();
                }
            }
            $oBestellstatus = PluginHelper::getDB()->select('tbestellstatus', 'kBestellung', (int) $paymentSession->kBestellung);
            $url = Shop::Container()->getLinkService()->getSpecialPage($linktype_orderstatus)->getURL();
            header('Location: ' . $url . '?uid=' . $oBestellstatus->cUID);
            exit();
       } else {
            // If order is not finalized yet: redirect to processPaymentPage
            Shop::Container()->getLogService()->debug("Mollie - Order not finalized yet -> redirect to loading screen");
            header("Location: " . Shop::getURL() . '/' . PluginHelper::getPlugin()->getPluginID() . '/processPayment?hash=' . $sessionHash);
            exit();
        }
    }

    // Clean up Queue from external cron job
    if (array_key_exists('mollie_cleanup_cron', $_REQUEST)) {
        exit((string) QueueModel::cleanUp());
    }

} catch (Exception $e) {
    Shop::Container()->getLogService()->error($e->getMessage() . " (Trace: {$e->getTraceAsString()})");
}
