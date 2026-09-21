<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Command;

/**
 * Immutable result of ONE provider refund interaction (v2/refund plus the
 * immediate v2/query_refund when ZaloPay answers PROCESSING).
 *
 * TASK-CG6BM7 corrective round: RefundCommand is provider-only — it reports
 * the outcome instead of persisting pending state; the CreditmemoRefundPlugin
 * owns the lifecycle decision (SUCCESS -> core accounting, PROCESSING ->
 * durable pending refund, FAIL/TRANSPORT -> refusal evidence).
 *
 * @api
 * @since 1.0.0
 */
class RefundOutcome
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PROCESSING = 'processing';

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $mRefundId;

    /**
     * @var int
     */
    private $vndAmount;

    /**
     * @var string|null
     */
    private $queryPayload;

    /**
     * @param string $status One of the STATUS_* constants.
     * @param string $mRefundId Merchant refund id sent to the provider.
     * @param int $vndAmount Refund amount in VND (unit: dong, as provider expects).
     * @param string|null $queryPayload Serialized v2/query_refund request payload
     *        the cron replays (with a fresh MAC) to reconcile this refund.
     */
    public function __construct(
        string $status,
        string $mRefundId,
        int $vndAmount,
        ?string $queryPayload = null
    ) {
        $this->status = $status;
        $this->mRefundId = $mRefundId;
        $this->vndAmount = $vndAmount;
        $this->queryPayload = $queryPayload;
    }

    /**
     * Outcome status: STATUS_SUCCESS (money confirmed refunded) or
     * STATUS_PROCESSING (provider accepted, still running).
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * The m_refund_id used for this provider refund (query_refund key).
     *
     * @return string
     */
    public function getMRefundId(): string
    {
        return $this->mRefundId;
    }

    /**
     * Refund amount in VND.
     *
     * @return int
     */
    public function getVndAmount(): int
    {
        return $this->vndAmount;
    }

    /**
     * The serialized v2/query_refund payload carried for the pending track
     * (null on the SUCCESS path - the refund is already final).
     *
     * @return string|null
     */
    public function getQueryPayload(): ?string
    {
        return $this->queryPayload;
    }
}
