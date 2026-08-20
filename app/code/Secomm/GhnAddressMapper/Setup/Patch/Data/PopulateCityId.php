<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Psr\Log\LoggerInterface;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;

/**
 * Backfills the locale-independent `city_id` column from the legacy `city_name`
 * column (which may hold either the default/English name or a locale-specific name),
 * then deduplicates mappings that collapse onto the same city_id.
 */
class PopulateCityId implements DataPatchInterface, PatchRevertableInterface
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
        $cityTable = $this->moduleDataSetup->getTable('directory_region_city');
        $cityNameTable = $this->moduleDataSetup->getTable('directory_region_city_name');

        $connection->beginTransaction();
        try {
            // Pass 1: resolve via default_name within the same region (the value
            // actually stored on quote/order addresses at runtime).
            $connection->query(
                "UPDATE {$mappingTable} AS m
                 INNER JOIN {$cityTable} AS c
                    ON m.city_name = c.default_name AND m.region_id = c.region_id
                 SET m.city_id = c.city_id
                 WHERE m.city_id IS NULL"
            );

            // Pass 2: fallback — resolve rows still unresolved via the locale-name
            // table (rows imported while a non-default locale was active).
            $connection->query(
                "UPDATE {$mappingTable} AS m
                 INNER JOIN {$cityNameTable} AS cn ON m.city_name = cn.name
                 INNER JOIN {$cityTable} AS c ON cn.city_id = c.city_id AND m.region_id = c.region_id
                 SET m.city_id = c.city_id
                 WHERE m.city_id IS NULL"
            );

            // Dedup: collapse multiple rows that now share the same city_id
            // (legacy data could hold both the default name and a locale name for
            // the same city). Keep the enabled row with the smallest entity_id.
            $connection->query(
                "DELETE m1 FROM {$mappingTable} AS m1
                 INNER JOIN {$mappingTable} AS m2
                    ON m1.city_id = m2.city_id
                   AND m1.city_id IS NOT NULL
                   AND (
                       m1.status < m2.status
                       OR (m1.status = m2.status AND m1.entity_id > m2.entity_id)
                   )"
            );

            // Report rows that could not be resolved so an admin can fix them by hand.
            $select = $connection->select()
                ->from($mappingTable, ['entity_id', 'region_id', 'city_name'])
                ->where('city_id IS NULL');
            $unresolved = $connection->fetchAll($select);
            foreach ($unresolved as $row) {
                $this->logger->warning(
                    sprintf(
                        'GhnAddressMapper: could not resolve city_id for mapping entity_id=%1$d (region_id=%2$d, city_name="%3$s"). Please fix manually.',
                        $row['entity_id'],
                        $row['region_id'],
                        $row['city_name']
                    )
                );
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function revert()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $mappingTable = $this->moduleDataSetup->getTable(LocationMappingResource::TABLE_NAME);
        $connection->query("UPDATE {$mappingTable} SET city_id = NULL");
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
