<?php
/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie;

use JTL\Checkout\Bestellung;
use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Plugin\Helper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\ws5_mollie\lib\Hook\ApplePay;
use Plugin\ws5_mollie\lib\Model\QueueModel;
use Plugin\ws5_mollie\lib\Order;

class Bootstrap extends Bootstrapper
{

    /** @var Dispatcher */
    protected $dispatcher;

    public function boot(Dispatcher $dispatcher)
    {

        parent::boot($dispatcher);
        $this->dispatcher = $dispatcher;

        require_once __DIR__ . '/vendor/autoload.php';

        $saveToQueue = function ($hook, $args_arr, $type = 'hook') {
            $mQueue = QueueModel::newInstance(Shop::Container()->getDB());
            $mQueue->setType($type . ':' . $hook);
            $mQueue->setData(serialize($args_arr));
            $mQueue->setCreated(date('Y-m-d H:i:s'));
            return $mQueue->save();
        };

        $this->listen(HOOK_SMARTY_OUTPUTFILTER, [ApplePay::class, 'execute']);

        $this->listen(HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB, function ($args_arr) {
            if (array_key_exists('oBestellung', $args_arr)) {
                /** @var Bestellung $oBestellung */
                $oBestellung = $args_arr['oBestellung'];
                if (Order::isMollie((int)$args_arr['oBestellung']->kZahlungsart)) {
                    $oBestellung->cAbgeholt = 'Y';
                    $args_arr['oBestellung']->cAbgeholt = 'Y';
                    Shop::Container()->getLogService()->info('Switch cAbgeholt for kBestellung: ' . print_r($args_arr, 1));
                }
            }
        })
            ->listen(HOOK_INDEX_NAVI_HEAD_POSTGET, function ($args_arr) use ($saveToQueue) {
                if (array_key_exists('mollie', $_REQUEST) && (int)$_REQUEST['mollie'] === 1 && array_key_exists('id', $_REQUEST)) {
                    $saveToQueue($_REQUEST['id'], $_REQUEST['id'], 'webhook');
                    exit();
                }
            })
            ->listen(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, function ($args_arr) use ($saveToQueue) {
                if (Order::isMollie((int)$args_arr['oBestellung']->kZahlungsart)) {
                    $saveToQueue(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, $args_arr);
                }
            })
            //->listen(HOOK_BESTELLUNGEN_XML_BEARBEITEUPDATE, function ($args_arr) use ($saveToQueue) {
            //    // TODO: Check if this is mollie
            //    $saveToQueue(HOOK_BESTELLUNGEN_XML_BEARBEITEUPDATE, $args_arr);
            //})
            ->listen(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, function ($args_arr) use ($saveToQueue) {
                // TODO: Check if this is mollie
                $saveToQueue(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, $args_arr);
            });

    }

    protected function listen(int $hook, callable $listener, int $priority = 5): Bootstrap
    {
        if ($this->dispatcher) {
            try {
                $this->dispatcher->listen('shop.hook.' . $hook, $listener, $priority);
            } catch (\Exception $e) {
                if (\Shop::isFrontend()) {
                    \Shop::Container()->getBackendLogService()->addCritical($e->getMessage());
                } else {
                    \Shop::Container()->getLogService()->addCritical($e->getMessage());
                }
            }
        }
        return $this;
    }

    public function renderAdminMenuTab(string $tabName, int $menuID, JTLSmarty $smarty): string
    {
        switch ($tabName) {
            case 'Dashboard':
                $oPlugin = Helper::getPluginById("ws5_mollie");
                $info = null;
                if ($oPlugin) {
                    $info = (object)[
                        'id' => $oPlugin->getID(),
                        'token' => $_SESSION['jtl_token'],
                        'endpoint' => $oPlugin->getPaths()->getAdminURL() . 'api.php',
                        'pluginID' => $oPlugin->getPluginID(),
                        'version' => $oPlugin->getCurrentVersion()->getOriginalVersion(),
                        'name' => $oPlugin->getMeta()->getName(),
                        'svg' => http_build_query([
                            'p' => $oPlugin->getPluginID(),
                            'v' => $oPlugin->getCurrentVersion()->getOriginalVersion(),
                            's' => APPLICATION_VERSION,
                            //'b' => JTL_MINOR_VERSION,
                            'd' => self::getDomain(),
                            'm' => base64_encode(self::getMasterMail(true)),
                            'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION . PHP_EXTRA_VERSION,
                        ])
                    ];
                }

                $js = array_filter(($dirJS = scandir(__DIR__ . '/adminmenu/app/build/static/js')) ? array_map(function ($file) {
                    return preg_match('/^(2|main)\.[a-f0-9]{8}\.chunk\.js$/i', $file) ? $file : null;
                }, $dirJS) : []);

                $css = array_filter(($dirCSS = scandir(__DIR__ . '/adminmenu/app/build/static/css')) ? array_map(function ($file) {
                    return preg_match('/^(2|main)\.[a-f0-9]{8}\.chunk\.css$/i', $file) ? $file : null;
                }, $dirCSS) : []);

                Shop::Smarty()
                    ->assign('css', $css)
                    ->assign('js', $js)
                    ->assign("infoJSON", json_encode($info))
                    ->assign('root', $oPlugin->getPaths()->getAdminURL());
                return Shop::Smarty()->fetch($oPlugin->getPaths()->getAdminPath() . '/root.tpl');
            default:
                return parent::renderAdminMenuTab($tabName, $menuID, $smarty); // TODO: Change the autogenerated stub
        }
    }

    public static function getDomain($url = URL_SHOP)
    {

        $matches = array();
        @preg_match("/^((http(s)?):\/\/)?(www\.)?([a-zA-Z0-9-\.]+)(\/.*)?$/i", $url, $matches);
        return strtolower(isset($matches[5]) ? $matches[5] : $url);
    }

    public static function getMasterMail($e = false)
    {
        $settings = \Shop::getSettings(array(CONF_EMAILS));
        $mail = trim($settings['emails']['email_master_absender']);
        if ($e === true && $mail != '') {
            $mail = base64_encode($mail);
            $eMail = "";
            foreach (str_split($mail, 1) as $c) {
                $eMail .= chr(ord($c) ^ 0x00100110);
            }
            return base64_encode($eMail);
        }
        return $mail;
    }

}
