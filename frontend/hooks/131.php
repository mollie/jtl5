<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\ExclusiveLock;
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

    if (PluginHelper::getSetting('queue') === 'sync') {
        ifndef('MOLLIE_QUEUE_MAX', 3);
        /** @noinspection PhpUndefinedConstantInspection */
        Queue::runSynchronous(MOLLIE_QUEUE_MAX);
    }

    //TODO: remove this check in next version
    // forces v5.1 Shop to abschlussseite
    if (PluginHelper::isShopVersionEqualOrGreaterThan("5.2.0")) {
        //eigentlich nicht notwendig, aber naja
        ifndef('LINNKTYPE_BESTELLABSCHLUSS', 33);
        ifndef('LINKTYP_BESTELLSTATUS', 38);
        $linktype_ordercompleted = (int) LINKTYP_BESTELLABSCHLUSS;
        $linktype_orderstatus = (int) LINKTYP_BESTELLSTATUS;

        if (array_key_exists('hash', $_REQUEST) && (UrlHelper::urlHasSpecialPageLinkType($linktype_ordercompleted) || UrlHelper::urlHasSpecialPageLinkType($linktype_orderstatus))) {
            $sessionHash = trim(Text::htmlentities(Text::filterXSS($_REQUEST['hash'])), '_');
            $paymentSession = PluginHelper::getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash);
            if ($paymentSession && $paymentSession->kBestellung) {
                $oBestellung = new \JTL\Checkout\Bestellung($paymentSession->kBestellung);

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
            }
        }
    } else {
        //eigentlich nicht notwendig, aber naja
        ifndef('LINNKTYPE_BESTELLABSCHLUSS', 33);
        $linktype_ordercompleted = (int) LINKTYP_BESTELLABSCHLUSS;


        if (array_key_exists('hash', $_REQUEST) && (UrlHelper::urlHasSpecialPageLinkType($linktype_ordercompleted))) {
            $sessionHash = trim(Text::htmlentities(Text::filterXSS($_REQUEST['hash'])), '_');
            $paymentSession = PluginHelper::getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash);
            if ($paymentSession && $paymentSession->kBestellung) {
                $oBestellung = new \JTL\Checkout\Bestellung($paymentSession->kBestellung);

                $oBestellID = PluginHelper::getDB()->select('tbestellid', 'kBestellung', $paymentSession->kBestellung);
                if ($oBestellID) {
                    $url = Shop::Container()->getLinkService()->getSpecialPage($linktype_ordercompleted)->getURL();
                    header('Location: ' . $url . '?i=' . $oBestellID->cId);
                    exit();
                }

            }
        }
    }

    if (array_key_exists('mollie_cleanup_cron', $_REQUEST)) {
        exit((string) QueueModel::cleanUp());
    }

} catch (Exception $e) {
    Shop::Container()->getLogService()->error($e->getMessage() . " (Trace: {$e->getTraceAsString()})");
}
