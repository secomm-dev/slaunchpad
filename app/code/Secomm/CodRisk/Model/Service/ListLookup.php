<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Secomm\CodRisk\Model\CodRiskList;
use Secomm\CodRisk\Model\ResourceModel\CodRiskList\CollectionFactory;

/**
 * Indexed lookup for active list records (phone + website + active + effective window).
 */
class ListLookup
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    /**
     * Website 0 rows act as global; a website-specific row wins. Effective window:
     * from/to NULL = open-ended. Returns the newest winning record.
     * websiteId null = inspect across all websites (evaluation tooling only —
     * the checkout path always passes a concrete website id).
     */
    public function findActive(string $normalizedPhone, ?int $websiteId, string $listType): ?CodRiskList
    {
        if ($normalizedPhone === '') {
            return null;
        }

        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('normalized_phone', $normalizedPhone)
            ->addFieldToFilter('list_type', $listType)
            ->addFieldToFilter('is_active', 1);

        if ($websiteId !== null) {
            $collection->addFieldToFilter('website_id', ['in' => array_values(array_unique([0, (int)$websiteId]))]);
        }

        $collection->getSelect()
            ->where('effective_from IS NULL OR effective_from <= ?', $today)
            ->where('effective_to IS NULL OR effective_to >= ?', $today)
            // Cast below operates on the already-filtered int set.
            ->order(new \Zend_Db_Expr('CASE WHEN website_id = ' . (int)($websiteId ?? 0) . ' THEN 0 ELSE 1 END'))
            ->order('entity_id DESC')
            ->limit(1);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }
}
