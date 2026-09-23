<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * DEC-FEATYA2C0W-004 (D2/D4/D9) / TASK-AQT7V3 — carrier-neutral outcome of one shipping address
 * resolution, handed down to carrier address mappers in Phase E-B.
 *
 * Status semantics are REUSED from Secomm_VietNamAddress (no parallel constants):
 * - EXACT    same-scheme resolution / direct canonical identity (unitCode set);
 * - MAPPED   exactly one deterministic target candidate (unitCode set);
 * - AMBIGUOUS multiple target candidates (candidateCodes — NEVER auto-picked);
 * - UNMAPPED no target candidate at all.
 *
 * Canonical boundary is scheme_code + unit_code only. Region and district are DERIVABLE through
 * Secomm_VietNamAddress (VnAddressUnitProviderInterface::getUnit() exposes region_code and
 * parent_code) and are therefore deliberately absent here — no names, no provider IDs, no
 * district duplication.
 */
interface ResolvedShippingAddressInterface
{
    /** EXACT | MAPPED | AMBIGUOUS | UNMAPPED — @see VnAddressResolutionInterface::STATUS_* */
    public function getStatus(): string;

    /** Scheme the resolution is expressed in (the carrier-required scheme when resolved). */
    public function getSchemeCode(): string;

    /**
     * Resolved canonical ward-level unit code — set for EXACT/MAPPED only.
     * Null for AMBIGUOUS/UNMAPPED: a candidate is never silently exposed as resolved.
     */
    public function getUnitCode(): ?string;

    /**
     * All candidate target codes for AMBIGUOUS (deterministically sorted by the resolver,
     * order preserved); empty for EXACT/MAPPED/UNMAPPED.
     *
     * @return string[]
     */
    public function getCandidateCodes(): array;

    /** Centralized resolved-state guard: true only for EXACT/MAPPED. */
    public function isResolved(): bool;
}
