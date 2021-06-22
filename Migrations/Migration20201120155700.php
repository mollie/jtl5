<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20201120155700 extends Migration implements IMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` ADD `fAmount` float NULL AFTER `cHash`, ADD `fAmountRefunded` float NULL, ADD `cCurrency` VARCHAR(3), ADD `cLocale` VARCHAR(5), ADD `cBestellNr` VARCHAR(128), ADD `cMethod` VARCHAR(32) AFTER `fAmount`;');
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_orders` DROP `fAmount`, DROP `fAmountRefunded`, DROP `cCurrency`, DROP `cLocale`, DROP `cMethod`, DROP `cBestellNr`;');
    }

    public function getDescription(): string
    {
        return 'Extend Order Plugin-Tables (Shop<->Mollie)';
    }
}
