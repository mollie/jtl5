<?php
/**
 * @copyright 2021 WebStollen GmbH
 */

namespace Plugin\ws5_mollie\lib\Order;

use Plugin\ws5_mollie\lib\Traits\Jsonable;
use Shop;

class Amount implements \JsonSerializable
{
    use Jsonable;

    public $value;
    public $currency;

    /**
     * Amount constructor.
     * @param $value
     * @param \Currency $currency
     * @param bool      $useFactor
     * @param bool      $useRounding (is it total SUM => true [5 Rappen Rounding])
     * @todo: prüfe mit Shop4
     */
    public function __construct($value, \Currency $currency, bool $useFactor = true, bool $useRounding = false)
    {
        if ($useFactor) {
            $value *= $currency->getConversionFactor();
        }
        if ($useRounding) {
            $value = self::optionaleRundung($value);
        }
        $this->value = number_format($value, 2, '.', '');

        $this->currency = $currency->getCode();
    }

    /**
     * @param float $gesamtsumme
     * @return float
     */
    public static function optionaleRundung(float $gesamtsumme): float
    {
        $conf = Shop::getSettings([CONF_KAUFABWICKLUNG]);
        if (isset($conf['kaufabwicklung']['bestellabschluss_runden5']) && $conf['kaufabwicklung']['bestellabschluss_runden5'] == 1) {
            // simplification. see https://de.wikipedia.org/wiki/Rundung#Rappenrundung
            $gesamtsumme = round($gesamtsumme * 20.0) / 20.0;
        }

        return $gesamtsumme;
    }
}
