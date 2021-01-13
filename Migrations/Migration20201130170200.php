<?php

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20201130170200 extends Migration implements IMigration
{

    public function up()
    {
        $this->execute('CREATE TABLE IF NOT EXISTS `xplugin_ws5_mollie_queue` (
                `kId` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `cType` VARCHAR(32) NOT NULL,
                `cData` TEXT DEFAULT \'\',
                `cResult` TEXT NULL DEFAULT NULL,
                `dDone`  DATETIME NULL DEFAULT NULL,
                `dCreated` DATETIME NOT NULL,
                `dModified` DATETIME NULL DEFAULT NULL 
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;');
    }

    public function down()
    {
        $this->execute('DROP TABLE IF EXISTS `xplugin_ws5_mollie_queue`;');
    }

    public function getDescription(): string
    {
        return 'Queue Plugin-Table (WAWI<->Shop<->Mollie)';
    }
}