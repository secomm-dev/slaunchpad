<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Parameter;

use JsonException;
use Secomm\UiWidget\Api\ParameterCodecInterface;

/**
 * Canonical JSON and Base64URL codec for widget directive parameters.
 */
class Codec implements ParameterCodecInterface
{
    public const MAX_ENCODED_BYTES = 16384;
    public const MAX_DEPTH = 6;
    public const MAX_ITEMS = 50;

    /**
     * @inheritDoc
     */
    public function encode(array $data): string
    {
        if (!$this->isWithinLimits($data)) {
            return '';
        }

        $envelope = ['data' => $this->canonicalize($data), 'version' => self::FORMAT_VERSION];
        try {
            $json = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '';
        }

        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return strlen($encoded) <= self::MAX_ENCODED_BYTES ? $encoded : '';
    }

    /**
     * @inheritDoc
     */
    public function decode(string $payload): ?array
    {
        if ($payload === '' || strlen($payload) > self::MAX_ENCODED_BYTES
            || !preg_match('/^[A-Za-z0-9_-]+$/', $payload)
        ) {
            return null;
        }

        $padding = (4 - strlen($payload) % 4) % 4;
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $json = base64_decode(strtr($payload . str_repeat('=', $padding), '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        try {
            $envelope = json_decode($json, true, self::MAX_DEPTH + 2, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($envelope) || ($envelope['version'] ?? null) !== self::FORMAT_VERSION
            || !isset($envelope['data']) || !is_array($envelope['data'])
            || ($envelope['data'] !== [] && !$this->isAssociative($envelope['data']))
            || !$this->isWithinLimits($envelope['data'])
        ) {
            return null;
        }

        return $envelope['data'];
    }

    /**
     * Sort associative keys recursively while preserving collection order.
     *
     * @param array $value Structured value.
     */
    private function canonicalize(array $value): array
    {
        if ($this->isAssociative($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }

    /**
     * Enforce depth, scalar-only leaves and collection item count.
     *
     * @param array $value Structured value.
     * @param int $depth Current nesting depth.
     */
    private function isWithinLimits(array $value, int $depth = 1): bool
    {
        if ($depth > self::MAX_DEPTH || (!$this->isAssociative($value) && count($value) > self::MAX_ITEMS)) {
            return false;
        }
        foreach ($value as $item) {
            if (is_array($item) && !$this->isWithinLimits($item, $depth + 1)) {
                return false;
            }
            if (!is_array($item) && !is_string($item) && !is_int($item)
                && !is_float($item) && !is_bool($item) && $item !== null
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether an array represents an object.
     *
     * @param array $value Structured value.
     */
    private function isAssociative(array $value): bool
    {
        return $value !== [] && array_keys($value) !== range(0, count($value) - 1);
    }
}
