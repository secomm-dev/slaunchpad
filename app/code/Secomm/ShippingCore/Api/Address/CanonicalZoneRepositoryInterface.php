<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — persistent merchant-managed canonical shipping zones.
 *
 * The repository works with the frozen domain VO (`CanonicalZoneInterface`) — the runtime
 * eligibility stack never sees the persistence model. Code lists are canonical VN_ADMIN_2025
 * codes only (`VN-XX` provinces, `VNA25-*` wards); provider-specific identities are rejected.
 * Every mutation flushes the `secomm_shippingcore_zones` cache type so runtime registries
 * observe the new state on the next request.
 */
interface CanonicalZoneRepositoryInterface
{
    /**
     * All persisted zones, enabled and disabled, deterministic order (code ASC).
     *
     * @return CanonicalZoneInterface[]
     */
    public function getAll(): array;

    /** @return CanonicalZoneInterface[]|null null = unknown code */
    public function getByCode(string $code): ?CanonicalZoneInterface;

    /**
     * Enabled zones for the given codes, INPUT order preserved, unknown/disabled skipped
     * (deterministic first-match eligibility semantics rely on caller-configured order).
     *
     * @param string[] $codes
     * @return CanonicalZoneInterface[]
     */
    public function getEnabledByCodes(array $codes): array;

    /**
     * Create (zoneId null) or update (zoneId given) a zone after full canonical validation
     * (normalized code uniqueness + reference-layer existence + membership rules).
     *
     * @param CanonicalZoneInterface $zone normalized domain values
     * @param int|null $zoneId null = create; given = update (must exist)
     * @return int zone_id
     * @throws \Magento\Framework\Exception\LocalizedException validation failure (rejects, never drops)
     * @throws \Magento\Framework\Exception\NoSuchEntityException unknown zoneId
     */
    public function save(CanonicalZoneInterface $zone, ?int $zoneId = null): int;

    /**
     * Enable/disable flag flip WITHOUT code-list re-validation (the codes are untouched;
     * mass actions must not be blocked by reference data drift that a full save would reject).
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException unknown zoneId
     */
    public function setEnabled(int $zoneId, bool $enabled): void;

    /** @throws \Magento\Framework\Exception\NoSuchEntityException unknown zoneId */
    public function deleteById(int $zoneId): void;
}
