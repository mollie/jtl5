<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Controller;

use Plugin\ws5_mollie\lib\Checkout\AbstractCheckout;
use stdClass;
use WS\JTL5\V2_1_4\Backend\AbstractResult;
use WS\JTL5\V2_1_4\Backend\Controller\AbstractController;
use WS\JTL5\V2_1_4\Exception\APIException;

class ShipmentsController extends AbstractController
{
    /**
     * Shipments API is removed — legacy ord_* must be completed in Mollie Dashboard.
     *
     * @param stdClass $data
     * @return AbstractResult
     * @throws APIException
     */
    public static function sync(stdClass $data): AbstractResult
    {
        throw new APIException(
            AbstractCheckout::LEGACY_ORDER_DEGRADE_MESSAGE
            . ' Shipments-Sync ist nicht mehr verfügbar — bitte Mollie Dashboard nutzen.'
        );
    }
}
