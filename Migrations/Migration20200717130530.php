<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20200717130530 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute(
            'CREATE TABLE IF NOT EXISTS `xplugin_ws5_mollie_orders` (
  `kId` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `kBestellung` int(11) DEFAULT NULL,
  `cOrderId` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `cTransactionId` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `cThirdId` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `cStatus` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `cHash` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `bTest` tinyint(1) NOT NULL,
  `bSynced` tinyint(1) NOT NULL DEFAULT 0,
  `dModified` datetime NOT NULL,
  `dCreated` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;'
        );

        $this->execute(
            'ALTER TABLE `xplugin_ws5_mollie_orders`
  ADD UNIQUE KEY `cOrderId` (`cOrderId`),
  ADD UNIQUE KEY `kBestellung` (`kBestellung`);'
        );
    }

    public function down()
    {
        $this->execute('DROP TABLE IF EXISTS `xplugin_ws5_mollie_orders`');
    }

    public function getDescription(): string
    {
        return 'Order Plugin-Tables (Shop<->Mollie)';
    }
}
