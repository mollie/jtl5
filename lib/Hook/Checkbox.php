<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Hook;

use Exception;
use JTL\Language\LanguageHelper;
use JTL\Session\Frontend;
use Plugin\ws5_mollie\lib\Model\CustomerModel;
use Plugin\ws5_mollie\lib\PluginHelper;
use WS\JTL5\V1_0_16\Hook\AbstractHook;

class Checkbox extends AbstractHook
{
    /**
     * @param $args_arr
     * @throws Exception
     */
    public static function execute(&$args_arr): void
    {
        if (!Frontend::get('Zahlungsart') || strpos(Frontend::get('Zahlungsart')->cModulId, 'kPlugin_' . PluginHelper::getPlugin()->getID() . '_') === false) {
            return;
        }

        if ($args_arr['nAnzeigeOrt'] === CHECKBOX_ORT_BESTELLABSCHLUSS && ($klarnaEID = trim(PluginHelper::getSetting('klarnaEID'))) !== '') {
            $modulID = Frontend::get('Zahlungsart')->cModulId;
            // TODO: Refactor this to use "PluginHelper::getPaymentSetting" once available
            $trustedShopsCheckbox = self::Plugin('ws5_mollie')->getConfig()->getValue($modulID . '_trustedShopsCheckbox');
            if ($trustedShopsCheckbox === 'Y') {
                $checkbox = new \JTL\CheckBox();
                $checkbox->kLink = 0;
                $checkbox->kCheckBox = 0;
                $checkbox->kCheckBoxFunktion = 0;
                $checkbox->cName = 'MOLLIE KLARNA AGB';
                $checkbox->cKundengruppe = ';1;';
                $checkbox->cAnzeigeOrt = ';2;';
                $checkbox->nAktiv = 1;
                $checkbox->nPflicht = 1;
                $checkbox->nLogging = 0;
                $checkbox->nSort = 999;
                $checkbox->dErstellt = date('Y-m-d H:i:s');
                $checkbox->oCheckBoxSprache_arr = [];

                $langs = LanguageHelper::getAllLanguages(1);
                foreach ($langs as $kSprache => $lang) {
                    $checkbox->oCheckBoxSprache_arr[$kSprache] = (object)[
                        'cText' => PluginHelper::getPlugin()->getLocalization()->getTranslation('klarnaAGBText', $lang->getIso()),
                        'cBeschreibung' => sprintf(PluginHelper::getPlugin()->getLocalization()->getTranslation('klarnaAGBDesc', $lang->getIso()), $klarnaEID),
                        'kSprache' => $kSprache,
                        'kCheckbox' => -1
                    ];
                }

                $checkbox->kKundengruppe_arr = [Frontend::getCustomer()->getGroupID()];
                $checkbox->kAnzeigeOrt_arr   = [CHECKBOX_ORT_BESTELLABSCHLUSS];
                $checkbox->cID               = 'mollie_klarna_agb';
                $checkbox->cLink             = '';

                $args_arr['oCheckBox_arr'][] = $checkbox;
            }
        }


        if (Frontend::getCustomer()->nRegistriert && $args_arr['nAnzeigeOrt'] === CHECKBOX_ORT_BESTELLABSCHLUSS) {
            $mCustomer = CustomerModel::fromID(Frontend::getCustomer()->getID(), 'kKunde');

            if ($mCustomer->customerId) {
                return;
            }

            $checkbox                       = new \JTL\CheckBox();
            $checkbox->kLink                = 0;
            $checkbox->kCheckBox            = 0;
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
                    'cText' => PluginHelper::getPlugin()->getLocalization()->getTranslation('checkboxText', $lang->getIso()),
                    'cBeschreibung' => PluginHelper::getPlugin()->getLocalization()->getTranslation('checkboxDescr', $lang->getIso()),
                    'kSprache' => $kSprache,
                    'kCheckbox' => -1
                ];
            }

            $checkbox->kKundengruppe_arr = [Frontend::getCustomer()->getGroupID()];
            $checkbox->kAnzeigeOrt_arr   = [CHECKBOX_ORT_BESTELLABSCHLUSS];
            $checkbox->cID               = 'mollie_create_customer';
            $checkbox->cLink             = '';

            $args_arr['oCheckBox_arr'][] = $checkbox;
        }
    }
}
