<?php


namespace Plugin\ws5_mollie\lib\Hook;


use JTL\Language\LanguageHelper;
use JTL\Link\Link;
use JTL\Model\DataModel;
use JTL\Shop;
use Plugin\ws5_mollie\lib\Model\CustomerModel;
use Session;

class Checkbox extends AbstractHook
{

    public static function execute(&$args_arr)
    {

        if (!Session::get('Zahlungsart') || strpos(Session::get('Zahlungsart')->cModulId, 'kPlugin_' . self::Plugin()->getID() . '_') === false) {
            return;
        }

        if ((int)Session::getCustomer()->nRegistriert && $args_arr['nAnzeigeOrt'] === CHECKBOX_ORT_BESTELLABSCHLUSS) {


            $mCustomer = CustomerModel::loadByAttributes([
                'kunde' => Session::getCustomer()->getID(),
            ], \Shop::Container()->getDB(), DataModel::ON_NOTEXISTS_NEW);

            if ($mCustomer->getCustomerId()) {
                return;
            }

            $checkbox = new \JTL\CheckBox();
            $checkbox->kLink = 0;
            $checkbox->kCheckBox = -1;
            $checkbox->kCheckBoxFunktion = 0;
            $checkbox->cName = 'MOLLIE SAVE CUSTOMER';
            $checkbox->cKundengruppe = ';1;';
            $checkbox->cAnzeigeOrt = ';2;';
            $checkbox->nAktiv = 1;
            $checkbox->nPflicht = 0;
            $checkbox->nLogging = 0;
            $checkbox->nSort = 999;
            $checkbox->dErstellt = date('Y-m-d H:i:s');
            $checkbox->oCheckBoxSprache_arr = [];

            $langs = LanguageHelper::getAllLanguages(1);
            foreach ($langs as $kSprache => $lang) {
                $checkbox->oCheckBoxSprache_arr[$kSprache] = (object)[
                    'cText' => self::Plugin()->getLocalization()->getTranslation('checkboxText', $lang->getIso()),
                    'cBeschreibung' => self::Plugin()->getLocalization()->getTranslation('checkboxDescr', $lang->getIso()),
                    'kSprache' => $kSprache,
                    'kCheckbox' => -1
                ];
            }

            $checkbox->kKundengruppe_arr = [Session::getCustomer()->getGroupID()];
            $checkbox->kAnzeigeOrt_arr = [CHECKBOX_ORT_BESTELLABSCHLUSS];
            $checkbox->cID = "mollie_create_customer";
            $checkbox->cLink = '';

            $args_arr['oCheckBox_arr'][] = $checkbox;

        }
    }
}
