<?php
/**
 * @copyright 2020 WebStollen GmbH
 */

namespace Plugin\ws5_mollie;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Plugin\Helper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\ws5_mollie\lib\Hook\ApplePay;
use Plugin\ws5_mollie\lib\Hook\Queue;

class Bootstrap extends Bootstrapper
{

    /** @var Dispatcher */
    protected $dispatcher;

    public function boot(Dispatcher $dispatcher)
    {

        parent::boot($dispatcher);
        $this->dispatcher = $dispatcher;

        require_once __DIR__ . '/vendor/autoload.php';

        $this->listen(HOOK_SMARTY_OUTPUTFILTER, [ApplePay::class, 'execute']);

        $this->listen(HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB, [Queue::class, 'bestellungInDB']);

        $this->listen(HOOK_INDEX_NAVI_HEAD_POSTGET, [Queue::class, 'headPostGet']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BESTELLSTATUS, [Queue::class, 'xmlBestellStatus']);

        $this->listen(HOOK_BESTELLUNGEN_XML_BEARBEITESTORNO, [Queue::class, 'xmlBearbeiteStorno']);

    }

    protected function listen(int $hook, callable $listener, int $priority = 5): Bootstrap
    {
        if ($this->dispatcher) {
            try {
                $this->dispatcher->listen('shop.hook.' . $hook, $listener, $priority);
            } catch (\Exception $e) {
                \Shop::Container()->getLogService()->error($e->getMessage());
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
                        'shopURL'  => Shop::getURL(),
                        'adminURL' => Shop::getAdminURL(),
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
                        ]),
                        /*'settings' => array_map(static function ($value) {
                            return $value->value;
                        }, $oPlugin->getConfig()->getAssoc())*/
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
                return parent::renderAdminMenuTab($tabName, $menuID, $smarty);
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
