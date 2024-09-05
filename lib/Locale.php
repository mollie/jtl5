<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib;

use WS\JTL5\V1_0_16\Traits\Plugins;

class Locale
{
    use Plugins;

    protected static $langs = [
        'ger' => ['lang' => 'de', 'country' => ['DE', 'AT', 'CH']],
        'fre' => ['lang' => 'fr', 'country' => ['BE', 'FR']],
        'dut' => ['lang' => 'nl', 'country' => ['BE', 'NL']],
        'spa' => ['lang' => 'es', 'country' => ['ES']],
        'ita' => ['lang' => 'it', 'country' => ['IT']],
        'pol' => ['lang' => 'pl', 'country' => ['PL']],
        'hun' => ['lang' => 'hu', 'country' => ['HU']],
        'por' => ['lang' => 'pt', 'country' => ['PT']],
        'nor' => ['lang' => 'nb', 'country' => ['NO']],
        'swe' => ['lang' => 'sv', 'country' => ['SE']],
        'fin' => ['lang' => 'fi', 'country' => ['FI']],
        'dan' => ['lang' => 'da', 'country' => ['DK']],
        'ice' => ['lang' => 'is', 'country' => ['IS']],
        'eng' => ['lang' => 'en', 'country' => ['GB', 'US']],
    ];

    /**
     * @param mixed       $cISOSprache
     * @param null|string $country
     * @return string
     */
    public static function getLocale($cISOSprache, ?string $country = null): string
    {
        if (array_key_exists($cISOSprache, self::$langs)) {
            $locale = self::$langs[$cISOSprache]['lang'];
            if ($country && is_array(self::$langs[$cISOSprache]['country']) && in_array($country, self::$langs[$cISOSprache]['country'], true)) {
                $locale .= '_' . strtoupper($country);
            } else {
                $locale .= '_' . self::$langs[$cISOSprache]['country'][0];
            }

            return $locale;
        }

        return PluginHelper::getSetting('fallbackLocale');
    }
}
