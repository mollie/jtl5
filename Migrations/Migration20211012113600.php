<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20211012113600 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_queue` ADD `cError` text NULL AFTER `cResult`;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_queue` DROP `cError`;');
    }

    public function getDescription(): string
    {
        return 'Extended Queue Table with Error-Field.';
    }
}
