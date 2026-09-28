<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Mapper;

use Magento\Framework\Exception\LocalizedException;
use Tiktok\Tiktok\Api\Export\ProductAttributeMapperInterface;
use Tiktok\Tiktok\Api\Export\ProductDataFormatterInterface;
use Tiktok\Tiktok\Helper\ProductMappingConfig;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\JsonProductDataProvider;
use Tiktok\Tiktok\Model\Config\ScopeManager;

/**
 * Product Attribute Mapper Abstract Class
 */
abstract class AbstractProductAttributeMapper implements ProductAttributeMapperInterface
{
    /**
     * Export Type
     */
    public const EXPORT_TYPE = null;

    /**
     * Header Name
     */
    public const CONFIG_HEADER_NAME = null;

    /**
     * @var \Tiktok\Tiktok\Model\Catalog\Export\Provider\JsonProductDataProvider
     */
    protected JsonProductDataProvider $dataProvider;

    /**
     * @var mixed
     */
    private $skippedProducts;

    /**
     * @var array|null
     */
    private ?array $customLabels;

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Api\Export\ProductDataFormatterInterface $exportFormatter
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Tiktok\Tiktok\Helper\ProductMappingConfig $mappingConfig
     * @param \Tiktok\Tiktok\Model\Config\ScopeManager $scopeManager
     */
    public function __construct(
        protected ProductDataFormatterInterface $exportFormatter,
        protected TiktokLogger $logger,
        protected ProductMappingConfig $mappingConfig,
        protected ScopeManager $scopeManager
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function mapPage($productCollection): ?array
    {
        if (!$productCollection) {
            return null;
        }
        $mappedData = [];
        foreach ($productCollection as $product) {
            $productData = $product->getData();
            $mappedRow = $this->mapRow($productData);
            if ($mappedRow !== null) {
                $mappedData[] = $mappedRow;
            } else {
                $this->skippedProducts++;
            }
        }
        return $mappedData;
    }

    /**
     * Map a products data to an array
     *
     * @param array $dataRow
     *
     * @return array|null
     */
    abstract public function mapRow(array $dataRow): ?array;

    /**
     * Return skipped products
     *
     * @return mixed
     */
    public function getSkippedProducts()
    {
        return $this->skippedProducts;
    }

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
    public function getMappedValue(array $dataRow, array $config, string $header, string $logLevel): mixed
    {
        $default = $config['default'] ?? null;
        $attributes = (array)($config['attribute'] ?? []);
        $formatter = $config['formatter'] ?? null;

        $attributeValues = array_map(static function ($attribute) use ($dataRow) {
            return $dataRow[$attribute] ?? null;
        }, $attributes);
        $value = $this->applyFormatter($formatter, $attributeValues);
        if ($value === null || $value === '') {
            $value = $default;
        }
        if ($this->isMissingRequiredData($value, $config)) {
            $this->logMissingValue($dataRow, $attributes, $header, $logLevel);
            return null;
        }
        return $value;
    }

    /**
     * Apply a formatter to attribute values if defined.
     *
     * @param string|null $formatter
     * @param array $attributeValues
     *
     * @return mixed
     */
    public function applyFormatter(?string $formatter, array $attributeValues): mixed
    {
        if ($formatter && method_exists($this->exportFormatter, $formatter)) {
            return $this->exportFormatter->{$formatter}(...$attributeValues);
        }

        return implode(' ', $attributeValues);
    }

    /**
     * Log a missing value for a required field.
     *
     * @param array $dataRow
     * @param array $attributes
     * @param string $header
     * @param string $logLevel
     */
    public function logMissingValue(array $dataRow, array $attributes, string $header, string $logLevel): void
    {
        if ($logLevel === 'debug') {
            $attributeList = implode(',', $attributes);
            $sku = $dataRow['sku'] ?? '';
            $this->logger->debug("Item '$sku' skipped: missing value for $header ($attributeList).");
        }
    }

    /**
     * Map custom label attributes into the given array.
     *
     * @param array $mappedData
     * @param array $dataRow
     */
    protected function mapCustomLabels(array &$mappedData, array $dataRow): void
    {
        foreach ($this->customLabels as $customLabel => $attributeCode) {
            $mappedData[$customLabel] = $dataRow[$attributeCode] ?? '';
        }
    }

    /**
     * Check if a value is required for the JSON export.
     *
     * @param mixed $value
     * @param array $config
     *
     * @return bool
     */
    public function isMissingRequiredData(mixed $value, array $config): bool
    {
        return empty($value) && isset($config['required'])
            && $config['required'] === self::EXPORT_TYPE && !isset($config['default']);
    }

    /**
     * Return mapped headers
     *
     * @return array
     */
    public function getMappedHeaders(): array
    {
        // Standard headers from the XML configuration
        $headers = array_keys($this->mappingConfig->getProductMapping());

        return array_merge($headers, $this->customLabels);
    }

    /**
     * Process mapping
     *
     * @param array $dataRow
     *
     * @return array|null
     */
    protected function processMapping(array $dataRow): ?array
    {
        $mappedData = [];
        $mapping = $this->mappingConfig->getProductMapping();
        $logLevel = $this->scopeManager->getLogLevel();

        foreach ($mapping as $header => $config) {
            if ($config['export_type'] !== 'both' && $config['export_type'] !== static::EXPORT_TYPE) {
                continue;
            }

            $headerName = $config[static::CONFIG_HEADER_NAME] ?? $header;
            $value = $this->getMappedValue($dataRow, $config, $headerName, $logLevel);

            if ($this->isMissingRequiredData($value, $config)) {
                return null;
            }

            $this->handleValue($mappedData, $headerName, $value, $config);
        }

        return $mappedData;
    }

    /**
     * Mapped value handler
     *
     * @param array $mappedData
     * @param string $headerName
     * @param mixed $value
     * @param array $config
     *
     * @return void
     */
    abstract protected function handleValue(array &$mappedData, string $headerName, mixed $value, array $config): void;

    /**
     * Gather attributes for export and headers
     *
     * @return array
     */
    public function getAttributesToSelect(): array
    {
        $attributesToSelect = [];
        $mapping = $this->mappingConfig->getProductMapping();
        foreach ($mapping as $config) {
            if (isset($config['attribute']) && !empty($config['attribute'])) {
                if (is_array($config['attribute'])) {
                    $attributesToSelect = $this->mergeAttributes($attributesToSelect, $config['attribute']);
                } elseif (($config['suppress_from_collection'] ?? false) !== true) {
                    $attributesToSelect[] = $config['attribute'];
                }
            }
        }
        $this->customLabels = $this->scopeManager->getCustomLabels();
        $customLabelAttributes = array_filter(array_values($this->customLabels));
        $attributesToSelect = array_merge($attributesToSelect, $customLabelAttributes);

        return array_unique($attributesToSelect);
    }

    /**
     * Merge attributes
     *
     * @param array $attributesToSelect
     * @param array $configAttribute
     * @return array
     */
    private function mergeAttributes(array $attributesToSelect, array $configAttribute): array
    {
        return array_merge($attributesToSelect, $configAttribute);
    }
}
