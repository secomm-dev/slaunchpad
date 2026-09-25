<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Model\ResourceModel\Product;

use Magento\Framework\App\ResourceConnection;

class ParentProductProvider
{
    /**
     * Table name of product links
     */
    private const PRODUCT_LINK_TABLE_NAME = 'catalog_product_super_link';

    /**
     * ParentProductProvider constructor
     *
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource,
    ) {
    }

    /**
     * Retrieve parent product data by product(child) ids
     *
     * @param array $childIds
     * @return array
     */
    public function getParentDataByChildIds($childIds)
    {
        if (empty($childIds)) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select();

        $select
            ->from(['cpsl' => $connection->getTableName(self::PRODUCT_LINK_TABLE_NAME)], [])
            ->join(
                ['e' => $connection->getTableName('catalog_product_entity')],
                'e.entity_id = cpsl.parent_id',
                ['child_id' => 'cpsl.product_id', 'parent_sku' => 'e.sku', 'parent_type' => 'type_id']
            )->where(
                'cpsl.product_id IN(?)',
                $childIds
            )->group('cpsl.product_id');

        return $connection->fetchAssoc($select);
    }
}
