<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * A ZaloPay refund response violated the provider protocol (TASK-CG6BM7
 * corrective round 7, F30): the v2/refund return_code is missing,
 * non-numeric, or outside the documented set {1=SUCCESS, 2=FAIL,
 * 3=PROCESSING}. The provider state of the refund is NOT confirmable -
 * which is explicitly NOT a confirmed refusal (that is return_code = 2
 * only, mapped through the safe status map as a plain LocalizedException).
 *
 * Extends LocalizedException so every Magento consumer (Payment::refund,
 * CreditmemoService, admin controller) keeps handling it as a
 * customer-safe failure; the dedicated type exists ONLY so the
 * CreditmemoRefundPlugin (and the RefundCronjob) can classify the
 * anomaly without string matching: a protocol anomaly lands the durable
 * UNKNOWN state (claim stays active, reconciliation by m_refund_id via
 * v2/query_refund) - never confirmed_fail, never a fresh /refund.
 *
 * The message is always our own safe text: raw provider payloads are
 * logged (never key material) and never reach the user-facing message.
 */
class RefundProtocolException extends LocalizedException
{
}
