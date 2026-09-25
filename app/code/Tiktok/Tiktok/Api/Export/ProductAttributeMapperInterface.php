<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Api\Export;

/**
 * Interface for Mapping Product Attributes for Export
 */
interface ProductAttributeMapperInterface
{
    /**
     * Fetch and map product data from the provider for a given page number.
     *
     * @param mixed $productCollection
     * @return array|null
     */
    public function mapPage(mixed $productCollection): ?array;

    /**
     * Map the product data to a flat array (CSV).
     *
     * @param array $dataRow
     *
     * @return array|null
     */
    public function mapRow(array $dataRow): ?array;

    /**
     * Mapped Headers
     *
     * @return array
     */
    public function getMappedHeaders(): array;

    /**
     * Determine the mapped value for a given configuration and data row.
     *
     * @param array $dataRow
     * @param array $config
     * @param string $header
     * @param string $logLevel
     *
     * @return mixed|null
     */
    public function getMappedValue(array $dataRow, array $config, string $header, string $logLevel): mixed;

    /**
     * Apply a formatter to attribute values if defined.
     *
     * @param string|null $formatter
     * @param array $attributeValues
     *
     * @return mixed
     */
    public function applyFormatter(?string $formatter, array $attributeValues): mixed;

    /**
     * Check if a value is required for the export.
     *
     * @param mixed $value
     * @param array $config
     *
     * @return bool
     */
    public function isMissingRequiredData(mixed $value, array $config): bool;

    /**
     * Log a missing value for a required field.
     *
     * @param array $dataRow
     * @param array $attributes
     * @param string $header
     * @param string $logLevel
     */
    public function logMissingValue(array $dataRow, array $attributes, string $header, string $logLevel): void;
}
