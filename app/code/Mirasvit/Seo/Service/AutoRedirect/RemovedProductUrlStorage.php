<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Seo\Service\AutoRedirect;

use Magento\Framework\App\ResourceConnection;

/**
 * Captures and reads the storefront request paths of products at delete time.
 *
 * Magento drops a product's url_rewrite rows on delete, so the source URL(s) must be persisted
 * before deletion to build the redirect afterwards. Retention is open-ended (a removed-product row
 * survives until its auto-rule is rebuilt); pruning is a product decision and not enforced here.
 */
class RemovedProductUrlStorage
{
    const TABLE = 'mst_seo_removed_product_url';

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * @param string[] $requestPaths
     */
    public function capture(int $productId, int $storeId, array $requestPaths): void
    {
        $rows = [];
        foreach ($requestPaths as $requestPath) {
            $requestPath = trim((string)$requestPath);
            if ($requestPath !== '') {
                $rows[] = [
                    'product_id'   => $productId,
                    'store_id'     => $storeId,
                    'request_path' => $requestPath,
                ];
            }
        }

        if ($rows) {
            $this->resource->getConnection()->insertMultiple(
                $this->resource->getTableName(self::TABLE),
                $rows
            );
        }
    }

    /**
     * Captured request paths for a product on a store view.
     *
     * @return string[]
     */
    public function getRequestPaths(int $productId, int $storeId): array
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        $select = $connection->select()
            ->from($table, ['request_path'])
            ->where('product_id = ?', $productId)
            ->where('store_id = ?', $storeId);

        return array_values(array_unique($connection->fetchCol($select)));
    }

    /**
     * Store ids for which a product has captured paths.
     *
     * @return int[]
     */
    public function getStoreIds(int $productId): array
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        $select = $connection->select()
            ->from($table, ['store_id'])
            ->where('product_id = ?', $productId)
            ->distinct();

        return array_map('intval', $connection->fetchCol($select));
    }

    public function clear(int $productId): void
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName(self::TABLE);

        $connection->delete($table, $connection->quoteInto('product_id = ?', $productId));
    }
}
