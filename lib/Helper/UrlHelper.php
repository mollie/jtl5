<?php

namespace Plugin\ws5_mollie\lib\Helper;

use JTL\Shop;

class UrlHelper
{
    /**
     * Checks if $_SERVER['REQUEST_URI'] contains one of the seo strings (for example "Bestellabschluss" or "Checkout") of the given link type
     * @param $linkType int from "defines_inc.php"
     * @return bool
     */
    public static function urlHasSpecialPageLinkType(int $linkType): bool
    {
        $seoLinkArray = Shop::Container()->getLinkService()->getSpecialPage($linkType)->getSEOs();

        if (!empty($seoLinkArray)) {
            foreach ($seoLinkArray as $seoLink) {
                if (strpos(strtolower($_SERVER['REQUEST_URI']), strtolower($seoLink)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
