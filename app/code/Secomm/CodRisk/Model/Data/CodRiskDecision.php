<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Data;

use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;

/**
 * Immutable evaluation result.
 */
final class CodRiskDecision implements CodRiskDecisionInterface
{
    /**
     * @param string $decision Self::ALLOW|WARNING|BLOCK
     * @param string|null $matchedRule Rule code that produced the base decision
     * @param string|null $reasonCode Business reason attached to the decision
     * @param int $historicalCount Events counted within lookback at evaluation time
     * @param bool $spamMatched Whether the spam rule matched
     * @param string|null $normalizedPhone
     * @param int|null $websiteId
     */
    public function __construct(
        private readonly string $decision,
        private readonly ?string $matchedRule = null,
        private readonly ?string $reasonCode = null,
        private readonly int $historicalCount = 0,
        private readonly bool $spamMatched = false,
        private readonly ?string $normalizedPhone = null,
        private readonly ?int $websiteId = null,
    ) {
    }

    public function getDecision(): string
    {
        return $this->decision;
    }

    public function getMatchedRule(): ?string
    {
        return $this->matchedRule;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function getHistoricalCount(): int
    {
        return $this->historicalCount;
    }

    public function isSpamMatched(): bool
    {
        return $this->spamMatched;
    }

    public function getNormalizedPhone(): ?string
    {
        return $this->normalizedPhone;
    }

    public function getWebsiteId(): ?int
    {
        return $this->websiteId;
    }
}