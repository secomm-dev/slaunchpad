<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Secomm\CodRisk\Model\ResourceModel\CodRiskEvent\CollectionFactory;

/**
 * Indexed event counting on secomm_cod_risk_event (phone + website + date index).
 * Never scans sales_order (CR-011, spec nguồn §19).
 */
class EventCounter
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    /**
     * @param string[]|null $reasonCodes null = any reason
     * @param bool $includeOnly true = only events snapshotted as include-in-count
     */
    public function countEvents(
        string $normalizedPhone,
        ?int $websiteId,
        \DateTimeImmutable $since,
        ?array $reasonCodes = null,
        bool $includeOnly = false,
    ): int {
        if ($normalizedPhone === '') {
            return 0;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('normalized_phone', $normalizedPhone)
            ->addFieldToFilter('created_at', ['gteq' => $since->format('Y-m-d H:i:s')]);

        if ($websiteId !== null) {
            $collection->addFieldToFilter('website_id', ['in' => array_values(array_unique([0, (int)$websiteId]))]);
        }

        if ($reasonCodes !== null) {
            if ($reasonCodes === []) {
                return 0;
            }
            $collection->addFieldToFilter('reason_code', ['in' => $reasonCodes]);
        }

        if ($includeOnly) {
            $collection->addFieldToFilter('include_snapshot', 1);
        }

        return (int)$collection->getSize();
    }
}
