<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Api\Data;

/**
 * Evaluation context. Risk identity = normalized shipping phone + website scope
 * (spec nguồn §5, §12) — never customer_id/email/device/IP as primary key.
 */
interface CodRiskContextInterface
{
    /**
     * Raw shipping phone as provided by the source (any format).
     */
    public function getRawPhone(): ?string;

    /**
     * Normalized phone (E.164 VN, e.g. +84901234567) or null when unusable.
     */
    public function getNormalizedPhone(): ?string;

    public function getWebsiteId(): ?int;

    public function getQuoteId(): ?int;

    public function getOrderId(): ?int;

    public function getCustomerId(): ?int;
}