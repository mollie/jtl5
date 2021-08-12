<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Mapper;

use Exception;
use WS\JTL5\Mapper\UpgradeMapper;

class MollieUpgradeMapper extends UpgradeMapper
{

    protected const MIN_UPGRADE_VERSION = 204;
    protected const MAX_UPGRADE_VERSION = 219;
    protected const UPGRADE_TYPES = [
        self::UPGRADE_TYPE_DATA,
        self::UPGRADE_TYPE_SYNC_SHIPPING,
        self::UPGRADE_TYPE_SETTINGS,
        self::UPGRADE_TYPE_PAYMENT_SETTINGS,
    ];

    /**
     * @return array<string>
     */
    public function getUpgradeTypes(): array
    {
        return array_merge(self::UPGRADE_TYPES, parent::getUpgradeTypes());
    }

    /**
     * @inheritDoc
     */
    public function mapPluginData($types = []): array
    {
        $errors = [];

        if (!count($types) || in_array(self::UPGRADE_TYPE_SETTINGS, $types, true)) {
            try {
                $settingsMap = [
                    'api_key' => 'apiKey',
                    'test_api_key' => 'test_apiKey',
                    'testAsAdmin' => 'testAsAdmin',
                    'paymentDescTpl' => 'paymentDescTpl',
                    'reminder' => 'reminder',
                    'autoStorno' => 'autoStorno',
                    'useCustomerAPI' => 'useCustomerAPI',
                    'profileId' => 'profileId',
                    'resetMethod' => 'resetMethod',
                    'onlyPaid' => 'onlyPaid',
                    'shippingMode' => 'shippingMode',
                    'shippingActive' => 'shippingActive',
                    'autoRefund' => 'autoRefund',
                    'checkoutMode' => 'checkoutMode',
                    'fallbackLocale' => 'fallbackLocale',
                ];
                $this->mapPluginSettings($settingsMap);
            } catch (Exception $e) {
                $errors[self::UPGRADE_TYPE_SETTINGS] = $e->getMessage();
            }
        }

        if (!count($types) || in_array(self::UPGRADE_TYPE_DATA, $types, true)) {
            try {
                $this->syncTableData('xplugin_ws_mollie_kunde', 'xplugin_ws5_mollie_kunde');
                $this->syncTableData('xplugin_ws_mollie_shipments', 'xplugin_ws5_mollie_shipments');
                $this->syncTableData('xplugin_ws_mollie_queue', 'xplugin_ws5_mollie_queue');
                $this->syncTableData('xplugin_ws_mollie_payments', 'xplugin_ws5_mollie_orders', 'null as kId, IF(`kBestellung`, `kBestellung`, null) as kBestellung, `kID` as cOrderId, `cTransactionId`, "" as cThirdId, `cStatus`, `cHash`,  `fAmount`, `cMethod`,  IF(`cMode` = "test", 1, 0) as bTest, `bSynced`, `dCreatedAt` as dModified, `dCreatedAt` as dCreated, `fAmountRefunded`, `cCurrency`, `cLocale`,  `cOrderNumber` as cBestellNr, `dReminder`');
            } catch (Exception $e) {
                $errors[self::UPGRADE_TYPE_DATA] = $e->getMessage();
            }
        }

        $oldModules = [
            self::nameToModulName('Mollie'),
            self::nameToModulName('Mollie Kreditkarte'),
            self::nameToModulName('Mollie Apple Pay'),
            self::nameToModulName('Mollie PayPal'),
            self::nameToModulName('Mollie Klarna Pay Later'),
            self::nameToModulName('Mollie Klarna Slice It'),
            self::nameToModulName('Mollie SOFORT'),
            self::nameToModulName('Mollie Przelewy24'),
            self::nameToModulName('Mollie Giropay'),
            self::nameToModulName('Mollie iDEAL'),
            self::nameToModulName('Mollie EPS'),
            self::nameToModulName('Mollie Bancontact'),
            self::nameToModulName('Mollie Banktransfer'),
            self::nameToModulName('Mollie Belfius'),
            self::nameToModulName('Mollie KBC'),
            self::nameToModulName('Mollie MyBank'),
            self::nameToModulName('Mollie paysafecard'),
        ];

        $newModules = [
            self::nameToModulName('Mollie'),
            self::nameToModulName('Creditcard'),
            self::nameToModulName('Apple Pay'),
            self::nameToModulName('PayPal'),
            self::nameToModulName('Klarna Pay Later'),
            self::nameToModulName('Klarna Slice It'),
            self::nameToModulName('SOFORT'),
            self::nameToModulName('Przelewy24'),
            self::nameToModulName('Giropay'),
            self::nameToModulName('iDEAL'),
            self::nameToModulName('EPS'),
            self::nameToModulName('Bancontact'),
            self::nameToModulName('Banktransfer'),
            self::nameToModulName('Belfius'),
            self::nameToModulName('KBC'),
            self::nameToModulName('MyBank'),
            self::nameToModulName('paysafe card'),
        ];

        if (!count($types) || in_array(self::UPGRADE_TYPE_PAYMENT_SETTINGS, $types, true)) {
            try {
                $paymentSettings = [];
                foreach ($oldModules as $i => $oldModule) {
                    $paymentSettings[$oldModule . '_min'] = $newModules[$i] . '_min';
                    $paymentSettings[$oldModule . '_max'] = $newModules[$i] . '_max';
                    $paymentSettings[$oldModule . '_min_bestellungen'] = $newModules[$i] . '_min_bestellungen';
                    $paymentSettings[$oldModule . '_dueDays'] = $newModules[$i] . '_dueDays';
                    // only CC
                    if ($newModules[$i] === 'creditcard') {
                        $paymentSettings[$oldModule . '_components'] = $newModules[$i] . '_components';
                        $paymentSettings[$oldModule . '_loadTrust'] = $newModules[$i] . '_trustBadge';
                    }
                    // NO KLARNA
                    if (stripos($newModules[$i], 'klarna') === false) {
                        $paymentSettings[$oldModule . '_api'] = $newModules[$i] . '_api';
                    }
                }

                $this->mapPaymentSettings($paymentSettings);
            } catch (Exception $e) {
                $errors[self::UPGRADE_TYPE_PAYMENT_SETTINGS] = $e->getMessage();
            }
        }

        if (!count($types) || in_array(self::UPGRADE_TYPE_SYNC_SHIPPING, $types, true)) {
            try {
                $this->syncShippingMethods(array_combine($oldModules, $newModules));
            } catch (Exception $e) {
                $errors[self::UPGRADE_TYPE_SYNC_SHIPPING] = $e->getMessage();
            }
        }


        return array_merge($errors, parent::mapPluginData($types));
    }
}
