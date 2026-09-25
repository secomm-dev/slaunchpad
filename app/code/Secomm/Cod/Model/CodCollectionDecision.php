<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Model;

use Secomm\Cod\Api\CodCollectionDecisionInterface;

/**
 * TASK-DFGFZ9 phase 2 (DEC-TASKDFGFZ9-002) — immutable collection decision; mirrors the
 * house decision-VO style (impossible states are unconstructible, named factories are the
 * ergonomic path). Construct via `collectible()` / `notCod()` / `rejected()` only.
 */
final class CodCollectionDecision implements CodCollectionDecisionInterface
{
    private function __construct(
        private readonly string $status,
        private readonly ?float $amount,
        private readonly ?string $currencyCode,
        private readonly ?string $rejectionReason,
        private readonly ?string $rejectionMessage
    ) {
    }

    public static function collectible(float $amount, string $currencyCode): self
    {
        if ($amount < 0.0) {
            throw new \LogicException(
                'A collectible COD decision requires a non-negative amount — zero is allowed '
                . '(a COD order with nothing to collect); use notCod() only for non-COD orders.'
            );
        }

        return new self(self::STATUS_COLLECTIBLE, $amount, $currencyCode, null, null);
    }

    public static function notCod(): self
    {
        return new self(self::STATUS_NOT_COD, null, null, null, null);
    }

    public static function rejected(string $reason, string $message): self
    {
        if (!in_array($reason, [self::REASON_CURRENCY_UNSUPPORTED, self::REASON_PARTIAL_SHIPMENT, self::REASON_COD_ALREADY_COLLECTED, self::REASON_INVALID_ORDER_AMOUNT], true)) {
            throw new \LogicException(sprintf('Unknown COD collection rejection reason "%s".', $reason));
        }

        return new self(self::STATUS_REJECTED, null, null, $reason, $message);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    public function isCollectible(): bool
    {
        return $this->status === self::STATUS_COLLECTIBLE;
    }

    /**
     * @inheritDoc
     */
    public function isNotCod(): bool
    {
        return $this->status === self::STATUS_NOT_COD;
    }

    /**
     * @inheritDoc
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }

    /**
     * @inheritDoc
     */
    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    /**
     * @inheritDoc
     */
    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /**
     * @inheritDoc
     */
    public function getRejectionMessage(): ?string
    {
        return $this->rejectionMessage;
    }
}
