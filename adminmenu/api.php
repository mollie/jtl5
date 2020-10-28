<?php

use JTL\Helpers\Form;
use Plugin\ws5_mollie\Lib\API;

/** @global \JTL\Backend\AdminAccount $oAccount */
require_once __DIR__ . '/../../../admin/includes/admininclude.php';

try {

    ob_start();

    if (strtolower($_SERVER['REQUEST_METHOD']) === 'options') {
        return;
    }

    if (!$oAccount->getIsAuthenticated()) {
        AdminIO::getInstance()->respondAndExit(new IOError('Not authenticated as admin.', 401));
    }
    if (!Form::validateToken()) {
        AdminIO::getInstance()->respondAndExit(new IOError('CSRF validation failed.', 403));
    }

    $body = file_get_contents('php://input');
    if ($data = json_decode($body)) {
        $response = API::run($data);
        AdminIO::getInstance()->respondAndExit($response);
    } else {
        throw new \RuntimeException('Invalid JSON.');
    }
    ob_end_clean();

} catch (Exception $e) {
    AdminIO::getInstance()->respondAndExit(new IOError($e->getMessage(), 500));
}