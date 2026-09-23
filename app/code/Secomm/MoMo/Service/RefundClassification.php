<?php
/**
 * Immutable result of a MoMo refund response classification.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Secomm\MoMo\Api\Data\RefundRequestInterface;

class RefundClassification
{
    /**
     * @param string $status One of RefundRequestInterface::STATUS_*.
     * @param string $reason One of RefundRequestInterface::REASON_*.
     * @param string|null $responseCode Raw provider resultCode.
     * @param string|null $responseMessage Raw provider message.
     * @param string|null $providerTransactionId MoMo transId of the refund (SUCCESS only).
     */
    public function __construct(
        public readonly string $status,
        public readonly string $reason,
        public readonly ?string $responseCode = null,
        public readonly ?string $responseMessage = null,
        public readonly ?string $providerTransactionId = null
    ) {
    }

    /**
     * Classification for a transport-level failure (timeout, HTTP error,
     * unreadable body): outcome unknown, never FAILED. The sanitized
     * transport detail is carried by the caller into last_error.
     *
     * @return self
     */
    public static function transportError(): self
    {
        return new self(
            RefundRequestInterface::STATUS_UNKNOWN,
            RefundRequestInterface::REASON_TRANSPORT_ERROR,
            null,
            null,
            null
        );
    }

    /**
     * Whether the provider confirmed the refund executed.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status === RefundRequestInterface::STATUS_SUCCESS;
    }
}
