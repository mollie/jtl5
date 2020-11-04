<?php

use JTL\Helpers\Form;
use Plugin\ws5_mollie\Lib\API;

if ($_SERVER['HTTP_HOST'] === 'localhost') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: *');
}

/** @global \JTL\Backend\AdminAccount $oAccount */
require_once __DIR__ . '/../../../admin/includes/admininclude.php';

try {

    ob_start();

    if (strtolower($_SERVER['REQUEST_METHOD']) === 'options') {
        return;
    }
    if ($_SERVER['HTTP_HOST'] !== 'localhost' || $_REQUEST['token'] !== 'development') {
        if (!$oAccount->getIsAuthenticated()) {
            throw new RuntimeException('Not authenticated as admin.', 401);
        }
        if (!Form::validateToken()) {
            throw new RuntimeException('CSRF validation failed.', 403);
        }
    }

    $body = file_get_contents('php://input');
    if ($data = json_decode($body)) {
        $response = API::run($data);
        AdminIO::getInstance()->respondAndExit($response);
    } else {
        throw new \RuntimeException('Invalid JSON.', 400);
    }
    ob_end_clean();

} catch (Exception $e) {
    AdminIO::getInstance()->respondAndExit(new IOError($e->getMessage(), $e->getCode()));
}