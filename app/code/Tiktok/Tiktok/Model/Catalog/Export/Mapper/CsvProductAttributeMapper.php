<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Mapper;

class CsvProductAttributeMapper extends AbstractProductAttributeMapper
{
    /**
     * Export Type
     */
    public const EXPORT_TYPE = 'csv';

    /**
     * CSV Header name
     */
    public const CONFIG_HEADER_NAME = 'csv_name';

    /**
     * Map the product data to a flat array (CSV).
     *
     * @param array $dataRow
     * @return array|null
     */
    public function mapRow(array $dataRow): ?array
    {
        $mappedData = $this->processMapping($dataRow);

        if ($mappedData === null) {
            return null;
        }

        $this->mapCustomLabels($mappedData, $dataRow);

        return $mappedData;
    }

    /**
     * @inheritDoc
     */
    protected function handleValue(array &$mappedData, string $headerName, $value, array $config): void
    {
        $mappedData[$headerName] = $value ?? '';
    }
}
