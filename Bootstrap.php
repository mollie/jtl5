<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie;

use JTL\Backend\Notification;
use JTL\Backend\NotificationEntry;
use JTL\DB\ReturnType;
use JTL\Events\Dispatcher;
use JTL\Exceptions\CircularReferenceException;
use JTL\Exceptions\ServiceNotFoundException;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Hook\ApplePay;
use Plugin\ws5_mollie\lib\Hook\Checkbox;
use Plugin\ws5_mollie\lib\Hook\Queue;
use Plugin\ws5_mollie\lib\Mapper\MollieUpgradeMapper;
use WS\JTL5\Mapper\UpgradeMapperInterface;
use WS\JTL5\Traits\Plugins;

require_once __DIR__ . '/vendor/autoload.php';

class Bootstrap extends \WS\JTL5\Bootstrap
{
    use Plugins;

    /**
     * @param Dispatcher $dispatcher
     * @throws CircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        if (!Shop::isFrontend()) {
            $notificationSetting = self::Plugin('ws5_mollie')->getConfig()->getValue("notifications");
            if (in_array($notificationSetting, ['Y', 'A']) && $authorized = Shop::Container()->getDB()->executeQuery('SELECT kId FROM xplugin_ws5_mollie_orders WHERE cStatus = "authorized" AND dCreated > DATE_SUB(NOW(), INTERVAL 30 DAY)', ReturnType::AFFECTED_ROWS)) {
                Notification::getInstance()->add(
                    NotificationEntry::TYPE_WARNING,
                    'Mollie Authorized Orders',
                    "Derzeit gibt es {$authorized} Bestellung(en), mit dem Status 'authorized' (in den letzten 30 Tagen).",
                    Shop::getURL() . '/admin/plugin.php?kPlugin=' . self::Plugin('ws5_mollie')->getID()
                );
            }

            if (in_array($notificationSetting, ['Y', 'Q']) && $errors = Shop::Container()->getDB()->executeQuery('SELECT kId FROM xplugin_ws5_mollie_queue WHERE cError IS NOT NULL AND dCreated > DATE_SUB(NOW(), INTERVAL 30 DAY);', ReturnType::AFFECTED_ROWS)) {
                Notification::getInstance()->add(
                    NotificationEntry::TYPE_DANGER,
                    'Mollie Queue with Errors',
                    "Derzeit gibt es {$errors} Warteschlangen-Einträge, mit einem Fehler (in den letzten 30 Tagen).",
                    Shop::getURL() . '/admin/plugin.php?kPlugin=' . self::Plugin('ws5_mollie')->getID()
                );
            }
        }

        $this->listen(HOOK_SMARTY_OUTPUTFILTER, [ApplePay::class, 'execute']);

        $this->listen(HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB, [Queue::class, 'bestellungInDB']);

        $this->listen(HOOK_INDEX_NAVI_HEAD_POSTGET, [Queue::class, 'headPostGet']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [Queue::class, 'xmlBestellStatus']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, [Queue::class, 'xmlBearbeiteStorno']);

        if ($this->getPlugin()->getConfig()->getValue('useCustomerAPI') === 'C') {
            $this->listen(HOOK_CHECKBOX_CLASS_GETCHECKBOXFRONTEND, [Checkbox::class, 'execute']);
        }
    }

    /**
     * @return null|UpgradeMapperInterface
     */
    public function getUpgradeMapper(): ?UpgradeMapperInterface
    {
        return new MollieUpgradeMapper('ws_mollie', 'ws5_mollie');
    }
}
