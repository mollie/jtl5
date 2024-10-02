<?php

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20240919161400 extends Migration implements IMigration
{

    /**
     * @inheritDoc
     */
    public function up()
    {
        $this->execute('CREATE INDEX idx_composite_with_order ON xplugin_ws5_mollie_queue (`dDone`, `bLock`, `cType`, `dCreated` DESC);');
    }

    public function down()
    {
        // No need to change since 'xplugin_ws5_mollie_orders' is removed in Migration where it is created, and we don't support downgrading of Plugins
    }
}