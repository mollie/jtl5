<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20210809150000 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` CHANGE `cHash` `cHash` varchar(40) NOT NULL AFTER `cStatus`;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` CHANGE `cHash` `cHash` varchar(32) NOT NULL AFTER `cStatus`;');
    }

    public function getDescription(): string
    {
        return 'Extend Table orders[cHash] to 40 chars.';
    }
}
