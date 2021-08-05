<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use JTL\Language\LanguageHelper;
use Plugin\ws5_mollie\lib\Model\CustomerModel;
use Session;

class Checkbox extends \WS\JTL5\Hook\AbstractHook
{
    /**
     * @param $args_arr
     * @throws \Exception
     */
    public static function execute(&$args_arr): void
    {
        if (!Session::get('Zahlungsart') || strpos(Session::get('Zahlungsart')->cModulId, 'kPlugin_' . self::Plugin('ws5_mollie')->getID() . '_') === false) {
            return;
        }

        if (Session::getCustomer()->nRegistriert && $args_arr['nAnzeigeOrt'] === CHECKBOX_ORT_BESTELLABSCHLUSS) {
            $mCustomer = CustomerModel::fromID(Session::getCustomer()->getID(), 'kKunde');

            if ($mCustomer->customerId) {
                return;
            }

            $checkbox                       = new \JTL\CheckBox();
            $checkbox->kLink                = 0;
            $checkbox->kCheckBox            = -1;
            $checkbox->kCheckBoxFunktion    = 0;
            $checkbox->cName                = 'MOLLIE SAVE CUSTOMER';
            $checkbox->cKundengruppe        = ';1;';
            $checkbox->cAnzeigeOrt          = ';2;';
            $checkbox->nAktiv               = 1;
            $checkbox->nPflicht             = 0;
            $checkbox->nLogging             = 0;
            $checkbox->nSort                = 999;
            $checkbox->dErstellt            = date('Y-m-d H:i:s');
            $checkbox->oCheckBoxSprache_arr = [];

            $langs = LanguageHelper::getAllLanguages(1);
            foreach ($langs as $kSprache => $lang) {
                $checkbox->oCheckBoxSprache_arr[$kSprache] = (object)[
                    'cText'         => self::Plugin('ws5_mollie')->getLocalization()->getTranslation('checkboxText', $lang->getIso()),
                    'cBeschreibung' => self::Plugin('ws5_mollie')->getLocalization()->getTranslation('checkboxDescr', $lang->getIso()),
                    'kSprache'      => $kSprache,
                    'kCheckbox'     => -1
                ];
            }

            $checkbox->kKundengruppe_arr = [Session::getCustomer()->getGroupID()];
            $checkbox->kAnzeigeOrt_arr   = [CHECKBOX_ORT_BESTELLABSCHLUSS];
            $checkbox->cID               = 'mollie_create_customer';
            $checkbox->cLink             = '';

            $args_arr['oCheckBox_arr'][] = $checkbox;
        }
    }
}
