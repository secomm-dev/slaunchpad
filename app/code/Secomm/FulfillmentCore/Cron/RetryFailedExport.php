<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Cron;

use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Model\Config\FulfillmentConfig;
use Secomm\FulfillmentCore\Model\Export\ExportOrchestrator;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory;

/**
 * Retries pending/failed Magento-origin export rows under max_attempts.
 */
class RetryFailedExport
{
    public function __construct(
        private readonly FulfillmentConfig $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly ExportOrchestrator $exportOrchestrator
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $maxAttempts = $this->config->getMaxAttempts();
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('origin', ExportPushStatus::ORIGIN_MAGENTO);
        $collection->addFieldToFilter(
            'push_status',
            ['in' => [ExportPushStatus::PENDING, ExportPushStatus::FAILED]]
        );
        $collection->addFieldToFilter('attempt_count', ['lt' => $maxAttempts]);
        $collection->setPageSize(100);

        /** @var FulfillmentExport $row */
        foreach ($collection as $row) {
            $this->exportOrchestrator->retryExport((int) $row->getEntityId());
        }
    }
}
