<?php
/**
 * Repository contract for MoMo creditmemo refund request rows.
 *
 * All reads/writes go through the independent (non-sales-transaction)
 * connection so refund evidence survives the CreditmemoService rollback.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Api;

use Secomm\MoMo\Api\Data\RefundRequestInterface;

/**
 * MoMo refund request repository.
 *
 * @api
 */
interface RefundRequestRepositoryInterface
{
    /**
     * Insert a new refund request row (pending/open). Sets the entity id.
     *
     * @param RefundRequestInterface $refund
     * @return RefundRequestInterface
     */
    public function insert(RefundRequestInterface $refund): RefundRequestInterface;

    /**
     * Load one row by entity id.
     *
     * @param int $entityId
     * @return RefundRequestInterface|null
     */
    public function getById(int $entityId): ?RefundRequestInterface;

    /**
     * Load one row by the minted MoMo requestId.
     *
     * @param string $requestId
     * @return RefundRequestInterface|null
     */
    public function getByRequestId(string $requestId): ?RefundRequestInterface;

    /**
     * Load the open (pending/unknown) row for a payment identity, if any.
     *
     * @param string $momoOrderRef
     * @param string $momoTransId
     * @return RefundRequestInterface|null
     */
    public function findOpenByPaymentIdentity(string $momoOrderRef, string $momoTransId): ?RefundRequestInterface;

    /**
     * Sum of SUCCESS refund amounts recorded for a payment identity.
     *
     * @param string $momoOrderRef
     * @param string $momoTransId
     * @return int
     */
    public function getSuccessfulTotal(string $momoOrderRef, string $momoTransId): int;

    /**
     * Relabel stale pending rows to unknown for a payment identity.
     *
     * A pending row older than the TTL means the process died mid-request:
     * the provider outcome can no longer be confirmed from this request.
     * Unknown rows keep blocking (open_flag stays 1) until resolved.
     *
     * @param string $momoOrderRef
     * @param string $momoTransId
     * @param int $staleSeconds
     * @return int Number of rows relabelled.
     */
    public function sweepStalePending(string $momoOrderRef, string $momoTransId, int $staleSeconds): int;

    /**
     * Finalize an OPEN row to its terminal state (success/failed).
     *
     * Guarded at the SQL level (open_flag = 1): only the first writer wins,
     * so concurrent callers and re-runs cannot overwrite a terminal verdict.
     *
     * @param RefundRequestInterface $refund Entity carrying the terminal fields.
     * @return bool Whether this call performed the transition.
     */
    public function finalize(RefundRequestInterface $refund): bool;

    /**
     * Relabel a pending row to unknown (guarded pending → unknown).
     *
     * @param RefundRequestInterface $refund Entity carrying reason/last_error.
     * @return bool Whether this call performed the transition.
     */
    public function markUnknown(RefundRequestInterface $refund): bool;

    /**
     * Backfill the creditmemo id on a row (post-commit linkage).
     *
     * @param int $entityId
     * @param int $creditmemoId
     * @return bool Whether a row was updated.
     */
    public function markCreditmemo(int $entityId, int $creditmemoId): bool;

    /**
     * List recent refund rows for operator tooling.
     *
     * @param string|null $status Optional status filter.
     * @param string|null $orderIncrementId Optional order increment id filter.
     * @param int $limit
     * @return RefundRequestInterface[]
     */
    public function getList(?string $status = null, ?string $orderIncrementId = null, int $limit = 50): array;
}
