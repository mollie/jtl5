<?php

use JTL\Helpers\Request;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use Plugin\ws5_mollie\lib\ExclusiveLock;
use Plugin\ws5_mollie\lib\Queue;

try {

    /** @var $oPlugin \JTL\Plugin\Plugin */
    global $oPlugin;

    if (Request::isAjaxRequest()) {
        return;
    }

    require_once __DIR__ . '/../../vendor/autoload.php';

    ifndef('MOLLIE_QUEUE_MAX', 3);
    Queue::run(MOLLIE_QUEUE_MAX);

    if (array_key_exists('hash', $_REQUEST) && strpos($_SERVER['PHP_SELF'], 'bestellabschluss.php') !== false) {
        $sessionHash = trim(StringHandler::htmlentities(StringHandler::filterXSS($_REQUEST['hash'])), '_');
        $paymentSession = Shop::Container()->getDB()->select('tzahlungsession', 'cZahlungsID', $sessionHash);
        if ($paymentSession && $paymentSession->kBestellung) {
            $oBestellung = new \JTL\Checkout\Bestellung($paymentSession->kBestellung);

            if (\JTL\Shopsetting::getInstance()->getValue(CONF_KAUFABWICKLUNG, 'bestellabschluss_abschlusseite') === 'A') {
                $oBestellID = Shop::Container()->getDB()->select('tbestellid', 'kBestellung', $paymentSession->kBestellung);
                if ($oBestellID) {
                    header(sprintf('Location: %s/bestellabschluss.php?i=%s', Shop::getURL(), $oBestellID->cId));
                    exit();
                }
            }
            $oBestellstatus = Shop::Container()->getDB()->select('tbestellstatus', 'kBestellung', (int)$paymentSession->kBestellung);
            header('Location: ' . Shop::getURL() . '/status.php?uid=' . $oBestellstatus->cUID);
            exit();
        }
    }

    ifndef('MOLLIE_REMINDER_PROP', 10);
    /** @noinspection PhpUndefinedConstantInspection */
    if (random_int(1, MOLLIE_REMINDER_PROP) % MOLLIE_REMINDER_PROP === 0) {
        $lock = new ExclusiveLock('mollie_reminder', PFAD_ROOT . PFAD_COMPILEDIR);
        if ($lock->lock()) {
            AbstractCheckout::sendReminders();
            Queue::storno((int)$oPlugin->getConfig()->getValue('autoStorno'));
        }
    }


} catch (Exception $e) {
    Shop::Container()->getLogService()->error($e->getMessage() . " (Trace: {$e->getTraceAsString()})");
}

