<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Api\Data;

/**
 * Normalized risk event value object handed to RiskEventRecorderInterface.
 */
interface RiskEventInterface
{
    public const SOURCE_CUSTOMER = 'CUSTOMER';
    public const SOURCE_ADMIN = 'ADMIN';
    public const SOURCE_SYSTEM = 'SYSTEM';
    public const SOURCE_CARRIER = 'CARRIER';
    public const SOURCE_IMPORT = 'IMPORT';

    public function getNormalizedPhone(): string;

    public function getWebsiteId(): ?int;

    /**
     * Internal business reason code (carrier-specific codes must be normalized
     * before reaching CodRisk — spec nguồn §14).
     */
    public function getReasonCode(): string;

    public function getSource(): string;

    public function getOrderId(): ?int;

    public function getQuoteId(): ?int;

    public function getCustomerId(): ?int;

    public function getNote(): string;
}