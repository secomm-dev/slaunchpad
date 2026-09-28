<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\TrustSignals\PartnerData;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\Store;
use Magento\Eav\Model\Config;

class GetNumberSkus
{
    /**
     * Product table alias
     */
    public const PRODUCT_TABLE_ALIAS = 'main_table';

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    private MetadataPool $metadataPool;

    /**
     * @var \Magento\Eav\Model\Config
     */
    private Config $eavConfig;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\EntityManager\MetadataPool $metadataPool
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Config $eavConfig,
        MetadataPool $metadataPool
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->metadataPool = $metadataPool;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Get all product Ids
     *
     * @param string $storeId
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(string $storeId): int
    {
        $productTableName = $this->resourceConnection->getTableName(
            'catalog_product_entity'
        );
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['main_table' => $productTableName], ['cnt' => 'COUNT(*)']);

        $linkField = $this->metadataPool->getMetadata(
            ProductInterface::class
        )->getLinkField();
        $statusAttribute = $this->eavConfig->getAttribute(
            Product::ENTITY,
            ProductInterface::STATUS
        );

        //Default value
        $select->joinLeft(
            ['status_global_attr' => $statusAttribute->getBackendTable()],
            "status_global_attr.{$linkField} = " . self::PRODUCT_TABLE_ALIAS . ".{$linkField}"
            . ' AND status_global_attr.attribute_id = ' . (int)$statusAttribute->getAttributeId()
            . ' AND status_global_attr.store_id = ' . Store::DEFAULT_STORE_ID,
            []
        );

        //Store value
        $select->joinLeft(
            ['status_attr' => $statusAttribute->getBackendTable()],
            "status_attr.{$linkField} = " . self::PRODUCT_TABLE_ALIAS . ".{$linkField}"
            . ' AND status_attr.attribute_id = ' . (int)$statusAttribute->getAttributeId()
            . ' AND status_attr.store_id = ' . $storeId,
            []
        );

        $select->where(
            'IFNULL(status_attr.value, status_global_attr.value) = ?',
            Status::STATUS_ENABLED
        );

        return (int)$connection->fetchOne($select);
    }
}
