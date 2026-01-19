<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Order;

use JTL\Shop;
use Normalizer;

/**
 * Class Address
 * @package Mollie\Order
 */
class Address extends \Plugin\ws5_mollie\lib\Payment\Address
{
    /**
     * @var null|string
     */
    public $organizationName;

    /**
     * @var null|string
     */
    public $title;

    /**
     * @var string
     */
    public $givenName;

    /**
     * @var string
     */
    public $familyName;

    /**
     * @var string
     */
    public $email;

    /**
     * @var null|string
     */
    public $phone;

    /**
     * Address constructor.
     * @param $address
     */
    public function __construct($address)
    {
        parent::__construct($address);

        $this->title = html_entity_decode(substr(trim(($address->cAnrede === 'm' ? Shop::Lang()->get('mr') : Shop::Lang()->get('mrs')) . ' ' . $address->cTitel), 0, 20)) ?? null;

        // Normalize and strip diacritics to comply with Mollie API restrictions
        // Handle null/empty values and ensure Normalizer doesn't return false
        $givenNameInput = (string)($address->cVorname ?? '');
        $familyNameInput = (string)($address->cNachname ?? '');
        
        // Normalize to decomposed form (NFKD) to separate base characters from diacritics
        $normalizedGivenName = Normalizer::normalize($givenNameInput, Normalizer::FORM_KD);
        $normalizedFamilyName = Normalizer::normalize($familyNameInput, Normalizer::FORM_KD);
        
        // Fallback to original if normalization fails (intl extension not available)
        // Still try to strip diacritics from original if normalization failed
        $normalizedGivenName = $normalizedGivenName !== false ? $normalizedGivenName : $givenNameInput;
        $normalizedFamilyName = $normalizedFamilyName !== false ? $normalizedFamilyName : $familyNameInput;
        
        // Remove combining marks (diacritics) to comply with Mollie API
        // This removes characters like Vietnamese tone marks (ễ, ạ, etc.)
        $strippedGivenName = preg_replace('/\p{Mn}/u', '', $normalizedGivenName);
        $strippedFamilyName = preg_replace('/\p{Mn}/u', '', $normalizedFamilyName);
        
        // Decode HTML entities and ensure non-empty result
        // If stripping resulted in empty string, use original (edge case)
        $this->givenName = trim(html_entity_decode($strippedGivenName ?: $givenNameInput)) ?: '';
        $this->familyName = trim(html_entity_decode($strippedFamilyName ?: $familyNameInput)) ?: '';

        $this->email = html_entity_decode($address->cMail) ?? null;

        if ($organizationName = isset($address->cFirma) ? trim($address->cFirma) : null) {
            $this->organizationName = html_entity_decode($organizationName);
        }
    }
}
