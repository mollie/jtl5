<?php

/**
 * @copyright 2021 WebStollen GmbH
 * @link https://www.webstollen.de
 */

namespace Plugin\ws5_mollie\lib\Checkout;

use Mollie\Api\Http\Data\Address as MollieAddress;
use Mollie\Api\Http\Data\DataCollection;
use Mollie\Api\Http\Data\Money;
use Mollie\Api\Http\Data\OrderLine as MollieOrderLine;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Plugin\ws5_mollie\lib\Order\Amount;
use Plugin\ws5_mollie\lib\Payment\OrderLine as WSOrderLine;
use stdClass;

/**
 * Builds typed Mollie v3 CreatePaymentRequest from PaymentCheckout state + method options.
 */
class PaymentRequestBuilder
{
    private const FIRST_CLASS_OPTION_KEYS = [
        'description',
        'locale',
        'method',
        'customerId',
        'issuer',
        'captureMode',
        'metadata',
        'redirectUrl',
        'cancelUrl',
        'webhookUrl',
        'restrictPaymentMethodsToCountry',
        'sequenceType',
        'mandateId',
        'profileId',
        'captureDelay',
    ];

    public static function fromCheckout(PaymentCheckout $checkout, array $paymentOptions = []): CreatePaymentRequest
    {
        [$firstClass, $additional] = self::splitOptions($paymentOptions);

        $description = $firstClass['description'] ?? $checkout->description;
        $locale = $firstClass['locale'] ?? $checkout->locale;
        $method = $firstClass['method'] ?? $checkout->method;
        $customerId = $firstClass['customerId'] ?? $checkout->customerId;
        $issuer = $firstClass['issuer'] ?? null;
        $captureMode = $firstClass['captureMode'] ?? $checkout->captureMode;
        $metadata = $firstClass['metadata'] ?? $checkout->metadata;
        $redirectUrl = $firstClass['redirectUrl'] ?? $checkout->redirectUrl;
        $cancelUrl = $firstClass['cancelUrl'] ?? $checkout->cancelUrl;
        $webhookUrl = $firstClass['webhookUrl'] ?? $checkout->webhookUrl;
        $restrictPaymentMethodsToCountry = $firstClass['restrictPaymentMethodsToCountry'] ?? null;
        $sequenceType = $firstClass['sequenceType'] ?? null;
        $mandateId = $firstClass['mandateId'] ?? null;
        $profileId = $firstClass['profileId'] ?? null;
        $captureDelay = $firstClass['captureDelay'] ?? null;

        if ($metadata !== null && !is_array($metadata)) {
            $metadata = (array)$metadata;
        }

        $amount = self::mapMoney($checkout->amount);
        $lines = self::mapLines($checkout->lines ?? []);
        $billingAddress = self::mapAddress($checkout->billingAddress);
        $shippingAddress = self::mapAddress($checkout->shippingAddress);

        return new CreatePaymentRequest(
            description: (string)$description,
            amount: $amount,
            redirectUrl: $redirectUrl !== null ? (string)$redirectUrl : null,
            cancelUrl: $cancelUrl !== null ? (string)$cancelUrl : null,
            webhookUrl: $webhookUrl !== null ? (string)$webhookUrl : null,
            lines: $lines,
            billingAddress: $billingAddress,
            shippingAddress: $shippingAddress,
            locale: $locale !== null ? (string)$locale : null,
            method: $method,
            issuer: $issuer !== null ? (string)$issuer : null,
            restrictPaymentMethodsToCountry: $restrictPaymentMethodsToCountry !== null ? (string)$restrictPaymentMethodsToCountry : null,
            metadata: $metadata,
            captureMode: $captureMode !== null ? (string)$captureMode : null,
            captureDelay: $captureDelay !== null ? (string)$captureDelay : null,
            applicationFee: null,
            routing: null,
            sequenceType: $sequenceType !== null ? (string)$sequenceType : null,
            mandateId: $mandateId !== null ? (string)$mandateId : null,
            customerId: $customerId !== null ? (string)$customerId : null,
            profileId: $profileId !== null ? (string)$profileId : null,
            additional: $additional,
        );
    }

    /**
     * Snapshot suitable for error logging (JSON-serializable).
     */
    public static function debugPayload(CreatePaymentRequest $request): array
    {
        $payload = $request->payload()->all();

        return self::normalizeForLog($payload);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private static function splitOptions(array $paymentOptions): array
    {
        $firstClass = [];
        $additional = [];

        foreach ($paymentOptions as $key => $value) {
            if (in_array($key, self::FIRST_CLASS_OPTION_KEYS, true)) {
                $firstClass[$key] = $value;
            } else {
                $additional[$key] = $value;
            }
        }

        return [$firstClass, $additional];
    }

    private static function mapMoney($amount): Money
    {
        if ($amount instanceof Money) {
            return $amount;
        }

        if ($amount instanceof Amount) {
            return new Money((string)$amount->currency, (string)$amount->value);
        }

        if ($amount instanceof stdClass || is_array($amount)) {
            $data = (array)$amount;

            return new Money((string)($data['currency'] ?? ''), (string)($data['value'] ?? ''));
        }

        throw new \InvalidArgumentException('Unsupported amount type for Mollie Money mapping.');
    }

    private static function mapAddress($address): ?MollieAddress
    {
        if ($address === null) {
            return null;
        }

        if ($address instanceof MollieAddress) {
            return $address;
        }

        if (is_object($address) && method_exists($address, 'toArray')) {
            return MollieAddress::fromArray($address->toArray());
        }

        if (is_object($address) && method_exists($address, 'jsonSerialize')) {
            return MollieAddress::fromArray($address->jsonSerialize());
        }

        if (is_array($address) || $address instanceof stdClass) {
            return MollieAddress::fromArray((array)$address);
        }

        return null;
    }

    /**
     * @param array<int, WSOrderLine|object> $lines
     */
    private static function mapLines(array $lines): ?DataCollection
    {
        if ($lines === []) {
            return null;
        }

        $mapped = [];
        foreach ($lines as $line) {
            $mapped[] = self::mapOrderLine($line);
        }

        return DataCollection::collect($mapped);
    }

    private static function mapOrderLine($line): MollieOrderLine
    {
        if ($line instanceof MollieOrderLine) {
            return $line;
        }

        $discountAmount = null;
        if (isset($line->discountAmount) && $line->discountAmount !== null) {
            $discountAmount = self::mapMoney($line->discountAmount);
        }

        $vatAmount = null;
        if (isset($line->vatAmount) && $line->vatAmount !== null) {
            $vatAmount = self::mapMoney($line->vatAmount);
        }

        return new MollieOrderLine(
            description: (string)$line->description,
            quantity: (int)$line->quantity,
            unitPrice: self::mapMoney($line->unitPrice),
            totalAmount: self::mapMoney($line->totalAmount),
            type: isset($line->type) ? (string)$line->type : null,
            quantityUnit: isset($line->quantityUnit) ? (string)$line->quantityUnit : null,
            discountAmount: $discountAmount,
            recurring: null,
            vatRate: isset($line->vatRate) ? (string)$line->vatRate : null,
            vatAmount: $vatAmount,
            sku: isset($line->sku) ? (string)$line->sku : null,
            imageUrl: isset($line->imageUrl) ? (string)$line->imageUrl : null,
            productUrl: isset($line->productUrl) ? (string)$line->productUrl : null,
        );
    }

    private static function normalizeForLog(mixed $value): mixed
    {
        if ($value instanceof Money || $value instanceof MollieAddress || $value instanceof MollieOrderLine) {
            return $value->toArray();
        }

        if ($value instanceof DataCollection) {
            return array_map([self::class, 'normalizeForLog'], $value->items);
        }

        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::normalizeForLog($v);
            }

            return $out;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return self::normalizeForLog($value->toArray());
        }

        return $value;
    }
}
