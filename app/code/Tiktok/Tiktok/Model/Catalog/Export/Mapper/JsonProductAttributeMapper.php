<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Mapper;

class JsonProductAttributeMapper extends AbstractProductAttributeMapper
{
    /**
     * Export Type
     */
    public const EXPORT_TYPE = 'json';

    /**
     * Config Header Name
     */
    public const CONFIG_HEADER_NAME = 'json_name';

    /**
     * Map the product data to a hierarchical structure.
     *
     * @param array $dataRow
     *
     * @return array|null
     */
    public function mapRow(array $dataRow): ?array
    {
        $mappedData = $this->processMapping($dataRow);

        if ($mappedData === null) {
            return null;
        }

        if (!isset($mappedData['extra_info'])) {
            $mappedData['extra_info'] = [];
        }
        $this->mapCustomLabels($mappedData['extra_info'], $dataRow);

        return !empty($mappedData) ? $mappedData : null;
    }

    /**
     * @inheritDoc
     */
    protected function handleValue(array &$mappedData, string $headerName, $value, array $config): void
    {
        if (empty($value)) {
            return;
        }

        $parent = $config['parent'] ?? null;
        if ($parent) {
            $mappedData[$parent][$headerName] = $value;
        } else {
            $mappedData[$headerName] = $value;
        }
    }
}
