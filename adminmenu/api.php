<?php
/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

use WS\JTL5\Backend\API;

if ($_SERVER['HTTP_HOST'] === 'localhost') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: *');
}

/** @global \JTL\Backend\AdminAccount $oAccount */
require_once __DIR__ . '/../../../admin/includes/admininclude.php';
ini_set('display_errors', 0);

try {
    API::Init('ws5_mollie');
} catch (Exception $exception) {
    Shop::Container()->getLogService()->error($exception->getMessage());
}
