<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Command;

/**
 * Prepared provider refund request identity (TASK-CG6BM7 corrective
 * round 3): everything the durable claim needs BEFORE any provider I/O.
 *
 * The plugin persists this identity (m_refund_id + reconciliation payload)
 * atomically BEFORE RefundCommand::executePrepared touches the network, so
 * a crash after the request leaves can always be resolved by querying the
 * SAME m_refund_id - never by issuing a fresh /refund.
 */
class RefundRequest
{
    /**
     * @param array $requestData The exact v2/refund request body (re-usable
     *        for executePrepared - never rebuilt, identity is stable).
     * @param string $mRefundId Stable provider request identity.
     * @param string|null $queryPayload Serialized v2/query_refund payload
     *        stored with the claim (re-signed per cron run).
     * @param RefundOutcome $tracking Carried outcome for the transport case.
     */
    public function __construct(
        private readonly array         $requestData,
        private readonly string        $mRefundId,
        private readonly ?string       $queryPayload,
        private readonly RefundOutcome $tracking
    ) {
    }

    /**
     * @return array
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    /**
     * @return string
     */
    public function getMRefundId(): string
    {
        return $this->mRefundId;
    }

    /**
     * @return string|null
     */
    public function getQueryPayload(): ?string
    {
        return $this->queryPayload;
    }

    /**
     * @return RefundOutcome
     */
    public function getTracking(): RefundOutcome
    {
        return $this->tracking;
    }
}
