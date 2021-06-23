<?php

/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20210409161100 extends Migration implements IMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_shipments` CHANGE `kLieferschien` `kLieferschein` int(11) NOT NULL FIRST;');
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        $this->execute('ALTER TABLE `xplugin_ws5_mollie_shipments` CHANGE `kLieferschein` `kLieferschien` int(11) NOT NULL FIRST;');
    }

    public function getDescription(): string
    {
        return "Fix 'shipments'-Column Typo";
    }
}
