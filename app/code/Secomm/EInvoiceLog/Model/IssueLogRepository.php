<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Model;

use Secomm\EInvoiceLog\Api\IssueLogRepositoryInterface;
use Secomm\EInvoiceLog\Model\ResourceModel\IssueLog as IssueLogResource;
use Secomm\EInvoiceLog\Model\ResourceModel\IssueLog\CollectionFactory;

/**
 * Repository for issue log persistence and lookups.
 */
class IssueLogRepository implements IssueLogRepositoryInterface
{
    /**
     * @param IssueLogResource $issueLogResource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly IssueLogResource $issueLogResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(IssueLog $log): IssueLog
    {
        $this->issueLogResource->save($log);

        return $log;
    }

    /**
     * @inheritdoc
     */
    public function findLatestByOrderId(int $orderId): ?IssueLog
    {
        return $this->loadFirstMatch($orderId);
    }

    /**
     * @inheritdoc
     */
    public function findOpenByOrderId(int $orderId): ?IssueLog
    {
        return $this->loadFirstMatch($orderId, [
            'in' => [IssueLog::STATUS_PENDING, IssueLog::STATUS_PROCESSING],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function findSuccessfulByOrderId(int $orderId): ?IssueLog
    {
        return $this->loadFirstMatch($orderId, IssueLog::STATUS_SUCCESS);
    }

    /**
     * @inheritdoc
     */
    public function countByOrderId(int $orderId): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);

        return (int) $collection->getSize();
    }

    /**
     * @inheritdoc
     */
    public function findByRefId(string $refId): ?IssueLog
    {
        if ($refId === '') {
            return null;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('ref_id', $refId);
        $collection->setOrder('entity_id', 'DESC');
        $collection->setPageSize(1);
        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * Load the newest log row matching order and optional status filter.
     *
     * @param int $orderId
     * @param string|array|null $statusFilter
     * @return IssueLog|null
     */
    private function loadFirstMatch(int $orderId, string|array|null $statusFilter = null): ?IssueLog
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        if ($statusFilter !== null) {
            $collection->addFieldToFilter('status', $statusFilter);
        }
        $collection->setOrder('entity_id', 'DESC');
        $collection->setPageSize(1);
        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }
}
