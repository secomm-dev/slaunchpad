<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

use Secomm\UiWidget\Api\ComponentDefinitionInterface;
use Secomm\UiWidget\Api\SchemaValidatorInterface;

/**
 * Normalizes allowlisted component fields and rejects invalid required data.
 */
class SchemaValidator implements SchemaValidatorInterface
{
    private const STRING_TYPES = [
        'text', 'textarea', 'trusted-rich-text', 'media', 'media-image', 'url', 'video-url',
    ];

    /**
     * @inheritDoc
     */
    public function validate(ComponentDefinitionInterface $definition, array $data): ?array
    {
        return $this->validateFields($definition->getFields(), $data);
    }

    /**
     * Validate an ordered field schema.
     *
     * @param array $fields Ordered field definitions.
     * @param array $data Component data.
     * @return array<string, mixed>|null
     */
    private function validateFields(array $fields, array $data): ?array
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !$this->isValidName($field['name'] ?? null)) {
                return null;
            }
            $name = $field['name'];
            $value = $data[$name] ?? ($field['default'] ?? null);
            if ($this->isMissing($value)) {
                if (!empty($field['required']) || $this->isRequiredWithPresent($field, $data)) {
                    return null;
                }
                continue;
            }
            $value = $this->validateValue($field, $value, $data);
            if ($value === null) {
                return null;
            }
            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * Validate one field value.
     *
     * @param array $field Field definition.
     * @param mixed $value Candidate value.
     */
    private function validateValue(array $field, mixed $value, array $data): mixed
    {
        $type = $field['type'] ?? '';
        if (in_array($type, self::STRING_TYPES, true)) {
            return $this->validateString($field, $value, $data);
        }
        if ($type === 'select') {
            return $this->validateSelect($field, $value);
        }
        if ($type === 'yesno') {
            return $this->validateBoolean($value);
        }
        if ($type === 'integer' || $type === 'decimal') {
            return $this->validateNumber($field, $value, $type === 'integer');
        }
        if ($type === 'collection') {
            return $this->validateCollection($field, $value);
        }

        return null;
    }

    /**
     * Validate a bounded string, URL or media reference.
     *
     * @param array $field Field definition.
     * @param mixed $value Candidate value.
     */
    private function validateString(array $field, mixed $value, array $data): ?string
    {
        if (!is_string($value) || strlen($value) > (int)($field['max_length'] ?? 4096)) {
            return null;
        }
        if (($field['type'] ?? '') === 'url' && !$this->isSafeUrl($value)) {
            return null;
        }
        if (($field['type'] ?? '') === 'video-url' && !$this->isSafeVideoUrl($field, $value, $data)) {
            return null;
        }
        if (in_array(($field['type'] ?? ''), ['media', 'media-image'], true) && (!$this->isSafeMedia($value)
            || preg_match('/[\x00-\x1F\x7F<>"\']/', $value))
        ) {
            return null;
        }

        return $value;
    }

    /**
     * Validate an allowlisted option.
     *
     * @param array $field Field definition.
     * @param mixed $value Candidate value.
     */
    private function validateSelect(array $field, mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $allowed = [];
        foreach (($field['options'] ?? []) as $option) {
            if (is_array($option) && isset($option['value']) && is_string($option['value'])) {
                $allowed[] = $option['value'];
            }
        }

        return in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * Normalize accepted boolean representations.
     *
     * @param mixed $value Candidate value.
     */
    private function validateBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1') {
            return true;
        }
        if ($value === 0 || $value === '0') {
            return false;
        }

        return null;
    }

    /**
     * Normalize and bound a numeric field.
     *
     * @param array $field Field definition.
     * @param mixed $value Candidate value.
     * @param bool $integer Whether an integer is required.
     */
    private function validateNumber(array $field, mixed $value, bool $integer): int|float|null
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }
        $options = $integer ? FILTER_VALIDATE_INT : FILTER_VALIDATE_FLOAT;
        $number = filter_var($value, $options);
        if ($number === false || isset($field['min']) && $number < $field['min']
            || isset($field['max']) && $number > $field['max']
        ) {
            return null;
        }

        return $integer ? (int)$number : (float)$number;
    }

    /**
     * Validate collection rows while preserving their order.
     *
     * @param array $field Field definition.
     * @param mixed $value Candidate rows.
     * @return array<int, array<string, mixed>>|null
     */
    private function validateCollection(array $field, mixed $value): ?array
    {
        $minItems = max((int)($field['min_items'] ?? 0), 0);
        $maxItems = min((int)($field['max_items'] ?? 50), 50);
        if (!is_array($value) || count($value) < $minItems || count($value) > $maxItems
            || !array_is_list($value)
        ) {
            return null;
        }
        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                return null;
            }
            $normalized = $this->validateFields($field['fields'] ?? [], $row);
            if ($normalized === null) {
                return null;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * Validate a schema field name.
     *
     * @param mixed $name Candidate name.
     */
    private function isValidName(mixed $name): bool
    {
        return is_string($name) && preg_match('/^[a-z][a-z0-9_]*$/', $name) === 1;
    }

    /**
     * Determine whether a value was omitted.
     *
     * @param mixed $value Candidate value.
     */
    private function isMissing(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Determine whether a compound peer makes this field required.
     *
     * @param array $field Field definition.
     * @param array $data Component data.
     */
    private function isRequiredWithPresent(array $field, array $data): bool
    {
        foreach (($field['required_with'] ?? []) as $peer) {
            if (is_string($peer) && !$this->isMissing($data[$peer] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Allow local URLs and explicitly approved external schemes.
     *
     * @param string $value URL value.
     */
    private function isSafeUrl(string $value): bool
    {
        if ($value === '' || str_starts_with($value, '#')
            || str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return true;
        }
        if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches)) {
            return !str_starts_with($value, '//');
        }

        return in_array(strtolower($matches[1]), ['http', 'https', 'mailto', 'tel'], true);
    }

    /**
     * Allow local media references and HTTP(S) URLs.
     *
     * @param string $value Media value.
     */
    private function isSafeMedia(string $value): bool
    {
        if ($value === '' || str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return true;
        }
        if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches)) {
            return !str_starts_with($value, '//');
        }

        return in_array(strtolower($matches[1]), ['http', 'https'], true);
    }

    /**
     * Validate a video URL against the selected allowlisted provider.
     *
     * @param array $field Field definition.
     * @param string $value Candidate URL.
     * @param array $data Sibling component data.
     */
    private function isSafeVideoUrl(array $field, string $value, array $data): bool
    {
        $providerField = $field['provider_field'] ?? null;
        $provider = is_string($providerField) ? ($data[$providerField] ?? null) : null;
        if (!is_string($provider) || !in_array($provider, ['youtube', 'vimeo'], true)) {
            return false;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($provider === 'youtube') {
            return in_array($host, ['youtube.com', 'www.youtube.com', 'youtu.be', 'www.youtu.be'], true)
                && preg_match('~(youtube\.com/(watch\?[^#]*v=|live/|v/|embed/)|youtu\.be/)[A-Za-z0-9_-]+~i', $value) === 1;
        }

        return in_array($host, ['vimeo.com', 'www.vimeo.com'], true)
            && preg_match('~vimeo\.com/(video/)?[0-9]+(?:[/?#]|$)~i', $value) === 1;
    }
}
