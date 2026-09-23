<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Data;

use Secomm\CodRisk\Api\Data\CodRiskContextInterface;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;

/**
 * Immutable evaluation context.
 */
final class CodRiskContext implements CodRiskContextInterface
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly ?string $rawPhone,
        private readonly ?int $websiteId = null,
        private readonly ?int $quoteId = null,
        private readonly ?int $orderId = null,
        private readonly ?int $customerId = null,
    ) {
    }

    public function getRawPhone(): ?string
    {
        return $this->rawPhone;
    }

    public function getNormalizedPhone(): ?string
    {
        return $this->rawPhone === null ? null : $this->phoneNormalizer->normalize($this->rawPhone);
    }

    public function getWebsiteId(): ?int
    {
        return $this->websiteId;
    }

    public function getQuoteId(): ?int
    {
        return $this->quoteId;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }
}