<?php


namespace Plugin\ws5_mollie\lib\Hook;


use JTL\Checkout\Bestellung;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\Order;


class Queue extends AbstractHook
{

    public static function bestellungInDB(array $args_arr): void
    {
        if (array_key_exists('oBestellung', $args_arr)) {
            /** @var Bestellung $oBestellung */
            $oBestellung = $args_arr['oBestellung'];
            if (Order::isMollie((int)$args_arr['oBestellung']->kZahlungsart)) {
                $oBestellung->cAbgeholt = 'Y';
                $args_arr['oBestellung']->cAbgeholt = 'Y';
                Shop::Container()->getLogService()->info('Switch cAbgeholt for kBestellung: ' . print_r($args_arr, 1));
            }
        }
    }

    public static function xmlBestellStatus(array $args_arr): void
    {
        if (Order::isMollie((int)$args_arr['oBestellung']->kZahlungsart)) {
            self::saveToQueue(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [
                'kBestellung' => $args_arr['oBestellung']->kBestellung,
                'status' => (int)$args_arr['status']
            ]);
        }
    }

    protected static function saveToQueue($hook, $args_arr, $type = 'hook'): bool
    {
        $mQueue = QueueModel::newInstance(Shop::Container()->getDB());
        $mQueue->setType($type . ':' . $hook);
        $mQueue->setData(serialize($args_arr));
        $mQueue->setCreated(date('Y-m-d H:i:s'));
        try {
            return $mQueue->save();
        } catch (\Exception $e) {
            Shop::Container()->getLogService()->error($e->getMessage() . '\n' . print_r($args_arr, 1));
            return false;
        }
    }

    public static function xmlBearbeiteStorno(array $args_arr): void
    {
        self::saveToQueue(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, ['kBestellung' => $args_arr['oBestellung']->kBestellung]);
    }

    public static function headPostGet(array $args_arr): void
    {
        if (array_key_exists('mollie', $_REQUEST) && (int)$_REQUEST['mollie'] === 1 && array_key_exists('id', $_REQUEST)) {
            self::saveToQueue($_REQUEST['id'], $_REQUEST['id'], 'webhook');
            exit();
        }
    }

}