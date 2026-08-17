<?php

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20250625103600 extends Migration implements IMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        $this->execute('ALTER TABLE xplugin_ws5_mollie_queue MODIFY COLUMN cType VARCHAR(128);');
    }

    public function down()
    {
        // No need to change since 'xplugin_ws5_mollie_orders' is removed in Migration where it is created, and we don't support downgrading of Plugins
    }
}