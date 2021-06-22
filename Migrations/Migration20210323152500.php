<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20210323152500 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` ADD `dReminder` datetime NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` DROP `dReminder`;');
    }

    public function getDescription(): string
    {
        return 'adds Payment Reminder Column';
    }
}
