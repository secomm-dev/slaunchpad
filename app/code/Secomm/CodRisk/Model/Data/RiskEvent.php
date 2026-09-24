<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Data;

use Secomm\CodRisk\Api\Data\RiskEventInterface;

/**
 * Immutable normalized risk event value object.
 */
final class RiskEvent implements RiskEventInterface
{
    public function __construct(
        private readonly string $normalizedPhone,
        private readonly string $reasonCode,
        private readonly string $source,
        private readonly ?int $websiteId = null,
        private readonly ?int $orderId = null,
        private readonly ?int $quoteId = null,
        private readonly ?int $customerId = null,
        private readonly string $note = '',
    ) {
    }

    public function getNormalizedPhone(): string
    {
        return $this->normalizedPhone;
    }

    public function getWebsiteId(): ?int
    {
        return $this->websiteId;
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getQuoteId(): ?int
    {
        return $this->quoteId;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getNote(): string
    {
        return $this->note;
    }
}