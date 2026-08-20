<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Psr\Log\LoggerInterface;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;

/**
 * Backfills missing `country_id` (defaults to 'VN') and `region_name`
 * (from `directory_country_region.default_name`) for existing location mappings.
 */
class PopulateMissingRegionNameAndCountryId implements DataPatchInterface, PatchRevertableInterface
{
    public function __construct(
        private ModuleDataSetupInterface $moduleDataSetup,
        private LoggerInterface $logger
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $mappingTable = $this->moduleDataSetup->getTable(LocationMappingResource::TABLE_NAME);
        $regionTable = $this->moduleDataSetup->getTable('directory_country_region');

        $connection->beginTransaction();
        try {
            // Update country_id to 'VN' if empty or NULL
            $connection->query(
                "UPDATE {$mappingTable}
                 SET country_id = 'VN'
                 WHERE country_id IS NULL OR country_id = ''"
            );

            // Update region_name from directory_country_region default_name where region_name IS NULL or empty
            $connection->query(
                "UPDATE {$mappingTable} AS m
                 INNER JOIN {$regionTable} AS r ON m.region_id = r.region_id
                 SET m.region_name = r.default_name
                 WHERE m.region_name IS NULL OR m.region_name = ''"
            );

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->error('GhnAddressMapper: Failed to populate region_name / country_id', ['exception' => $e]);
            throw $e;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function revert()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            PopulateCityId::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
