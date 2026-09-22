<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Api\Data;

/**
 * Result of a COD risk evaluation.
 *
 * Consumer mapping (LC-08 COD): ALLOW and WARNING keep COD available,
 * BLOCK hides COD only (spec nguồn §4.2, §13).
 */
interface CodRiskDecisionInterface
{
    public const ALLOW = 'ALLOW';
    public const WARNING = 'WARNING';
    public const BLOCK = 'BLOCK';

    public const REASON_NO_PHONE = 'no_phone';

    public function getDecision(): string;

    /**
     * Rule code that produced the base decision (e.g. blacklist, spam_order,
     * allowlist, historical) or null for a clean ALLOW.
     */
    public function getMatchedRule(): ?string;

    /**
     * Business reason code attached to the decision (e.g. the blacklisted
     * list record reason, threshold reason code) or null.
     */
    public function getReasonCode(): ?string;

    /**
     * Historical count within the lookback window at evaluation time.
     */
    public function getHistoricalCount(): int;

    public function isSpamMatched(): bool;

    public function getNormalizedPhone(): ?string;

    public function getWebsiteId(): ?int;
}