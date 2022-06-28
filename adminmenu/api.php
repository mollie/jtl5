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
ini_set('display_errors', array_key_exists('debug', $_REQUEST));

try {
    API::Init('ws5_mollie');
} catch (Exception $exception) {
    \JTL\Shop::Container()->getLogService()->error($exception->getMessage());
}
