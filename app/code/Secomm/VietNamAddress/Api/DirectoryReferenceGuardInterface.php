<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * DEC-FEATYA2C0W-004 (D7) / TASK-Q4B98P — extension point for the VN scheme-swap safety guard.
 *
 * Carrier (or any third-party) modules whose tables hold runtime directory references
 * (region_id / city_id foreign keys by convention) register an implementation of this
 * interface via DI on Secomm\VietNamAddress\Model\Import\VnAddressSchemeImporter
 * (argument "directoryReferenceGuards"). VietNamAddress orchestrates every registered
 * guard before destructive scheme operations and never names their tables.
 *
 * New carrier participation requires ONLY a DI contribution in the carrier module —
 * no edit inside Secomm_VietNamAddress.
 */
interface DirectoryReferenceGuardInterface
{
    /**
     * Assert that no row still references the runtime directory ids about to disappear.
     * All registered guards always run; VietNamAddress aggregates every violation into a
     * single LocalizedException so the operator sees all blocking tables at once.
     *
     * @param array<int, int> $regionIds directory_country_region.region_id values
     * @param array<int, int> $cityIds   directory_region_city.city_id values
     * @throws LocalizedException when references exist (message: count + table + column)
     */
    public function assertSafe(array $regionIds, array $cityIds): void;

    /**
     * Stable guard identity for aggregated errors and dry-run reports (e.g. the owning
     * module plus its table name).
     */
    public function getName(): string;
}
