<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Evaluation;

use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;

/**
 * Outcome of a single rule evaluation.
 *
 * A rule either does not match (continue), matches non-terminally (recorded for
 * reporting, pipeline continues), or matches terminally (pipeline stops and the
 * rule's decision becomes the base decision).
 */
final class RuleResult
{
    public function __construct(
        private readonly bool $matched = false,
        private readonly bool $terminal = false,
        private readonly string $decision = CodRiskDecisionInterface::ALLOW,
        private readonly string $ruleCode = '',
        private readonly ?string $reasonCode = null,
        private readonly int $historicalCount = 0,
    ) {
    }

    public static function noMatch(string $ruleCode, int $historicalCount = 0): self
    {
        return new self(matched: false, terminal: false, ruleCode: $ruleCode, historicalCount: $historicalCount);
    }

    public static function match(
        string $ruleCode,
        string $decision,
        ?string $reasonCode = null,
        int $historicalCount = 0,
        bool $terminal = true,
    ): self {
        return new self(
            matched: true,
            terminal: $terminal,
            decision: $decision,
            ruleCode: $ruleCode,
            reasonCode: $reasonCode,
            historicalCount: $historicalCount,
        );
    }

    public function isMatched(): bool
    {
        return $this->matched;
    }

    public function isTerminal(): bool
    {
        return $this->terminal;
    }

    public function getDecision(): string
    {
        return $this->decision;
    }

    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function getHistoricalCount(): int
    {
        return $this->historicalCount;
    }
}