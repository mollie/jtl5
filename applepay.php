<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

use Plugin\ws5_mollie\lib\Hook\ApplePay;

require_once __DIR__ . '/../../includes/globalinclude.php';

if (array_key_exists('available', $_REQUEST)) {
    ApplePay::setAvailable((bool)$_REQUEST['available']);
}
header('Content-Type: application/json');
echo json_encode(true);
