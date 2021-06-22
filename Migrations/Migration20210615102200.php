<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20210615102200 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_queue` ADD `bLock` datetime NULL DEFAULT NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_queue` DROP `bLock`;');
    }

    public function getDescription(): string
    {
        return 'Lock rows in Queue.';
    }
}
