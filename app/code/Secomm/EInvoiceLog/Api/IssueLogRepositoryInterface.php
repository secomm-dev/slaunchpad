<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Api;

use Secomm\EInvoiceLog\Model\IssueLog;

/**
 * Persistence access for electronic invoice issue logs.
 *
 * @api
 * @since 1.0.0
 */
interface IssueLogRepositoryInterface
{
    /**
     * Persist issue log row.
     *
     * @param \Secomm\EInvoiceLog\Model\IssueLog $log
     * @return \Secomm\EInvoiceLog\Model\IssueLog
     */
    public function save(IssueLog $log): IssueLog;

    /**
     * Latest log row for an order (any status).
     *
     * @param int $orderId
     * @return \Secomm\EInvoiceLog\Model\IssueLog|null
     */
    public function findLatestByOrderId(int $orderId): ?IssueLog;

    /**
     * Open log row (pending or processing) for an order.
     *
     * @param int $orderId
     * @return \Secomm\EInvoiceLog\Model\IssueLog|null
     */
    public function findOpenByOrderId(int $orderId): ?IssueLog;

    /**
     * Successful log row for an order.
     *
     * @param int $orderId
     * @return \Secomm\EInvoiceLog\Model\IssueLog|null
     */
    public function findSuccessfulByOrderId(int $orderId): ?IssueLog;

    /**
     * Count log rows for an order.
     *
     * @param int $orderId
     * @return int
     */
    public function countByOrderId(int $orderId): int;

    /**
     * Latest log row matching a RefID.
     *
     * @param string $refId
     * @return \Secomm\EInvoiceLog\Model\IssueLog|null
     */
    public function findByRefId(string $refId): ?IssueLog;
}
