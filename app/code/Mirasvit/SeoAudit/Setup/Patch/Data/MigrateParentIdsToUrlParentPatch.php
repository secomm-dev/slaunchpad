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


declare(strict_types=1);

namespace Mirasvit\SeoAudit\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;

/**
 * Backfill the normalized mst_seo_audit_url_parent relation table from the legacy
 * comma-separated parent_ids column, so existing audit data benefits from the
 * indexed parent lookup that replaces FIND_IN_SET. New rows are kept in sync by
 * UrlRepository::save(); this patch is the one-time migration of historical data.
 */
class MigrateParentIdsToUrlParentPatch implements DataPatchInterface
{
    private const BATCH_SIZE = 5000;

    private $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply()
    {
        $connection    = $this->moduleDataSetup->getConnection();
        $urlTable      = $this->moduleDataSetup->getTable(UrlInterface::TABLE_NAME);
        $relationTable = $this->moduleDataSetup->getTable(UrlInterface::PARENT_TABLE_NAME);

        $select = $connection->select()
            ->from($urlTable, [UrlInterface::ID, UrlInterface::PARENT_IDS])
            ->where(UrlInterface::PARENT_IDS . ' IS NOT NULL')
            ->where(UrlInterface::PARENT_IDS . " != ''");

        $rows = [];

        foreach ($connection->fetchAll($select) as $row) {
            $urlId     = (int)$row[UrlInterface::ID];
            $parentIds = array_unique(array_filter(
                array_map('intval', explode(',', (string)$row[UrlInterface::PARENT_IDS])),
                function ($parentId) {
                    return $parentId > 0;
                }
            ));

            foreach ($parentIds as $parentId) {
                $rows[] = [
                    UrlInterface::PARENT_REL_URL_ID    => $urlId,
                    UrlInterface::PARENT_REL_PARENT_ID => $parentId,
                ];
            }

            if (count($rows) >= self::BATCH_SIZE) {
                $connection->insertOnDuplicate($relationTable, $rows, [UrlInterface::PARENT_REL_URL_ID]);
                $rows = [];
            }
        }

        if ($rows) {
            $connection->insertOnDuplicate($relationTable, $rows, [UrlInterface::PARENT_REL_URL_ID]);
        }

        return $this;
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
