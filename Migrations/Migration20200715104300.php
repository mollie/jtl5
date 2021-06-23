<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20200715104300 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `xplugin_ws5_mollie_kunde` (
                `kKunde`     int         NOT NULL PRIMARY KEY,
                `customerId` varchar(32) NOT NULL UNIQUE 
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;'
        );
    }

    public function down()
    {
        $this->execute('DROP TABLE IF EXISTS `xplugin_ws5_mollie_kunde`');
    }

    public function getDescription(): string
    {
        return 'Customer Plugin-Tables (Shop<->Mollie)';
    }
}
