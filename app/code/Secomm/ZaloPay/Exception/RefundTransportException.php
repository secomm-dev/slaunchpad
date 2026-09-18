<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Exception;

use Magento\Framework\Exception\LocalizedException;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;

/**
 * A ZaloPay refund interaction failed at the transport layer (HTTP timeout,
 * network error, malformed provider envelope): the provider outcome is
 * UNKNOWN or transient - retryable, must consume the bounded query budget.
 *
 * Extends LocalizedException so every Magento consumer of the refund flow
 * (Payment::refund, CreditmemoService, admin controller) keeps handling it
 * as a customer-safe failure; the type exists ONLY so the RefundCronjob and
 * the CreditmemoRefundPlugin can classify the failure without string
 * matching (TASK-CG6BM7 corrective round: retry budget semantics).
 *
 * When the request identity (m_refund_id) and reconciliation payload were
 * already built before the transport failure, the exception carries them as
 * a PROCESSING RefundOutcome: the refund may already be accepted by the
 * provider, so it must be tracked and reconciled by m_refund_id - never
 * silently re-requested with a fresh id (double-refund guard).
 */
class RefundTransportException extends LocalizedException
{
    /**
     * @param \Magento\Framework\Phrase $phrase
     * @param \Exception|null $cause
     * @param RefundOutcome|null $outcome Tracking outcome when the request
     *        identity was already built (null: nothing to track durably).
     */
    public function __construct(
        \Magento\Framework\Phrase    $phrase,
        ?\Exception                  $cause = null,
        private readonly ?RefundOutcome $outcome = null
    ) {
        parent::__construct($phrase, $cause);
    }

    /**
     * The tracking outcome for this refund (null when the request identity
     * was never built - nothing durable to track).
     *
     * @return RefundOutcome|null
     */
    public function getOutcome(): ?RefundOutcome
    {
        return $this->outcome;
    }
}
