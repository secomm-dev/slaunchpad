<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

/**
 * DEC-FEATYA2C0W-004 (D3) / TASK-7AJ3K8 — the minimal shared shape of a carrier API profile.
 *
 * A profile is a COMPLETE capability bundle owned by the carrier module (adapter/endpoints,
 * address representation, mappers, validation policy are carrier-class members, NOT shared
 * config flags): selecting a profile switches all related behavior atomically. ShippingCore
 * only knows the two facts every orchestration decision needs — WHO the API generation is and
 * WHICH VN address scheme it speaks. Endpoint accessors etc. live on the carrier's concrete
 * profile class (e.g. Secomm\Ghtk\Model\GhtkApiProfile), never here.
 *
 * No registry, no config abstraction (D10): a carrier injects its own profile class directly;
 * multiple generations = multiple profile classes behind a carrier-owned config read.
 */
interface CarrierApiProfileInterface
{
    /** Identity of the API generation, e.g. "GHTK_2025", "GHN_PRE_2025". */
    public function getCode(): string;

    /** Canonical VN administrative scheme the API expects (VnSchemes catalog code); null when not VN-address-bound. */
    public function getAddressScheme(): ?string;
}
