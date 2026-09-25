<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Formatter;

/**
 * Catalog Product Sanitizer
 */
class Sanitizer
{
    /**
     * Sanitize value
     *
     * @param mixed $value
     *
     * @return string
     */
    public function sanitize(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $sanitizedValue = $this->convertToUtf8($value);
        $sanitizedValue = strip_tags($sanitizedValue);
        $sanitizedValue = trim($sanitizedValue);
        $sanitizedValue = str_replace('"', '""', $sanitizedValue);
        $sanitizedValue = $this->truncateValue($sanitizedValue);
        if (str_contains($sanitizedValue, ',')
            || str_contains($sanitizedValue, '"')
            || str_contains($sanitizedValue, "\n")) {
            $sanitizedValue = '"' . $sanitizedValue . '"';
        }

        return $sanitizedValue;
    }

    /**
     * Truncate value
     *
     * @param mixed $value
     * @param int $maxLength
     *
     * @return string
     */
    protected function truncateValue(mixed $value, int $maxLength = 500): string
    {
        return mb_substr($value, 0, $maxLength);
    }

    /**
     * Convert value to UTF8
     *
     * @param mixed $value
     *
     * @return array|false|mixed|mixed[]|string|string[]|null
     */
    protected function convertToUtf8(mixed $value)
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8');
        }
        return $value;
    }
}
