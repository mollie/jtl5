<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20211206112100 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` DROP INDEX `cOrderId`;');
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` DROP INDEX `kBestellung`;');
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` ADD UNIQUE(`kBestellung`, `cOrderId`);');
    }

    public function down()
    {
        // No need to change since 'xplugin_ws5_mollie_orders' is removed in Migration where it is created, and we don't support downgrading of Plugins
    }

    public function getDescription(): string
    {
        return 'Fix UNIQUE Index on Orders-Table.';
    }
}
