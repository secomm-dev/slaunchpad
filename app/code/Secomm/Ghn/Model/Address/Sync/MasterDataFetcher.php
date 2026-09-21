<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Sync;

use Magento\Framework\Exception\LocalizedException;

/**
 * SPEC-FEAT-FQWEQ3 §8 — one fetcher per GHN address model. Fetchers translate provider payloads
 * into the normalized unit-row shape below (parent-first order); they never touch the DB.
 *
 * Normalized row shape:
 * ```
 * [
 *   'provider_key'    => string,                       // unique within the scheme (UNIQUE key)
 *   'provider_id'     => string|null,                  // ProvinceID/DistrictID/_id
 *   'provider_code'   => string|null,                  // WardCode (legacy model only)
 *   'parent_key'      => string|null,                  // provider_key of the parent unit
 *   'depth'           => int,                          // 1=province, 2=district|ward, 3=ward
 *   'name'            => string,                       // GHN canonical name (verbatim)
 *   'extension_names' => string|null,                  // JSON array or null
 *   'status'          => string,                       // GhnSchemes::STATUS_* (ACTIVE|DISABLED)
 * ]
 * ```
 */
interface MasterDataFetcher
{
    /**
     * @param string $scheme GhnSchemes constant this fetcher serves
     * @return array<int, array<string, mixed>> parent-first normalized rows
     * @throws LocalizedException transport or payload-shape failure (fail loud — never partial sync)
     */
    public function fetch(string $scheme): array;

    /**
     * Scheme this fetcher serves (GhnSchemes constant).
     */
    public function supports(): string;
}
