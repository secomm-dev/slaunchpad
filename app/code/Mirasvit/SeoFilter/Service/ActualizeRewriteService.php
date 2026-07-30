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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Service;

use Magento\Framework\App\ResourceConnection;
use Mirasvit\SeoFilter\Api\Data\RewriteInterface;

class ActualizeRewriteService
{
    /** @var ResourceConnection */
    private $resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Remove alias rows whose `option` references an attribute option that no
     * longer exists in eav_attribute_option. When $attributeCode is given the
     * cleanup is limited to that attribute (used by the attribute-save plugin).
     *
     * @return int number of deleted rows
     */
    public function pruneOrphanedOptionAliases(?string $attributeCode = null): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName(RewriteInterface::TABLE_NAME);

        $select = $connection->select()->from(
            ['alias' => $table],
            [RewriteInterface::ID]
        )->joinLeft(
            ['opt' => $this->resourceConnection->getTableName('eav_attribute_option')],
            'alias.' . RewriteInterface::OPTION . ' = opt.option_id',
            []
        )->where(
            'opt.option_id IS NULL'
        )->where('alias.' . RewriteInterface::OPTION . ' REGEXP ?', '^[0-9]+$');

        if ($attributeCode !== null) {
            $select->where('alias.' . RewriteInterface::ATTRIBUTE_CODE . ' = ?', $attributeCode);
        }

        $rewriteIds = $connection->fetchCol($select);

        if (!count($rewriteIds)) {
            return 0;
        }

        return (int)$connection->delete(
            $table,
            [RewriteInterface::ID . ' IN (?)' => $rewriteIds]
        );
    }

    /**
     * Remove alias rows that belong to attributes no longer marked filterable.
     *
     * @return int number of deleted rows
     */
    public function pruneNonFilterableAttributeAliases(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName(RewriteInterface::TABLE_NAME);

        $subSelect = $connection->select()
            ->from(
                ['ea' => $this->resourceConnection->getTableName('eav_attribute')],
                [RewriteInterface::ATTRIBUTE_CODE]
            )
            ->join(
                ['cea' => $this->resourceConnection->getTableName('catalog_eav_attribute')],
                'cea.attribute_id = ea.attribute_id',
                []
            )
            ->where('cea.is_filterable = ?', 0);

        $nonFilterableAttrCodes = $connection->fetchCol($subSelect);

        if (empty($nonFilterableAttrCodes)) {
            return 0;
        }

        return (int)$connection->delete(
            $table,
            [RewriteInterface::ATTRIBUTE_CODE . ' IN (?)' => $nonFilterableAttrCodes]
        );
    }

    /**
     * Remove alias rows for grouped options that no longer exist in
     * mst_navigation_grouped_option (only when that table is present).
     *
     * @return int number of deleted rows
     */
    public function pruneOrphanedGroupedOptionAliases(): int
    {
        $connection     = $this->resourceConnection->getConnection();
        $table          = $this->resourceConnection->getTableName(RewriteInterface::TABLE_NAME);
        $groupTableName = $this->resourceConnection->getTableName('mst_navigation_grouped_option');

        if (!$connection->isTableExists($groupTableName)) {
            return 0;
        }

        $groupSelect = $connection->select()->from(
            ['alias' => $table],
            [RewriteInterface::ID]
        )->joinLeft(
            ['group_table' => $groupTableName],
            'alias.' . RewriteInterface::OPTION . ' = group_table.code',
            []
        )->where(
            'group_table.group_id IS NULL'
        )->where('alias.' . RewriteInterface::OPTION . ' NOT REGEXP ?', '^[0-9]+$');

        $groupRewriteIds = $connection->fetchCol($groupSelect);

        if (!count($groupRewriteIds)) {
            return 0;
        }

        return (int)$connection->delete(
            $table,
            [RewriteInterface::ID . ' IN (?)' => $groupRewriteIds]
        );
    }
}
