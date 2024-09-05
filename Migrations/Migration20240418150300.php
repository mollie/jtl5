<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20240418150300 extends Migration implements IMigration
{
    /**
    * @inheritDoc
    */
    public function up()
    {
        if (\method_exists('\JTL\Update\DBMigrationHelper', 'migrateToInnoDButf8')) {
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_kunde');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_orders');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_queue');  // TODO: remove this code when min. shop version is 5.3
            \JTL\Update\DBMigrationHelper::migrateToInnoDButf8('xplugin_ws5_mollie_shipments');  // TODO: remove this code when min. shop version is 5.3
        }
    }

    public function down()
    {
        // No need to change since 'xplugin_ws5_mollie_orders' is removed in Migration where it is created, and we don't support downgrading of Plugins
    }
}
