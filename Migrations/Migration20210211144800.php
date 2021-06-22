<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20210211144800 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute('CREATE TABLE IF NOT EXISTS `xplugin_ws5_mollie_shipments` (
                `kLieferschien` int(11) NOT NULL PRIMARY KEY,
                `kBestellung` int(11) NOT NULL,
                `cOrderId` VARCHAR(32) NOT NULL,
                `cShipmentId` VARCHAR(32) NOT NULL,
                `cCarrier` VARCHAR(255) DEFAULT \'\',
                `cCode` VARCHAR(255) DEFAULT \'\',
                `cUrl` VARCHAR(512) DEFAULT \'\',
                `dCreated` DATETIME NOT NULL,
                `dModified` DATETIME NULL DEFAULT NULL 
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;');
    }

    public function down()
    {
        $this->execute('DROP TABLE IF EXISTS `xplugin_ws5_mollie_shipments`;');
    }

    public function getDescription(): string
    {
        return 'Shipments-Table (WAWI<->Shop<->Mollie)';
    }
}
