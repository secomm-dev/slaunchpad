<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

/**
 * SPEC-FEAT-FQWEQ3 §4 — the resolved GHN identity for one canonical unit, per OPERATION:
 *  - rating/leadtime (legacy model): needs the full province_id + district_id + ward_code triple
 *    (hasCompleteLegacyTriple() must be true — a district-only answer is NOT resolved, SPIKE-9Z231Q);
 *  - create order (current model): needs the verbatim province/ward NAMEs (hasNewAddressNames(),
 *    DEC-FEATFQWEQ3-001).
 */
final class GhnLocation
{
    public function __construct(
        private readonly string $secommScheme,
        private readonly string $secommUnitCode,
        private readonly ?string $provinceId = null,
        private readonly ?string $districtId = null,
        private readonly ?string $wardCode = null,
        private readonly ?string $provinceName = null,
        private readonly ?string $wardName = null
    ) {
    }

    public function getSecommScheme(): string
    {
        return $this->secommScheme;
    }

    public function getSecommUnitCode(): string
    {
        return $this->secommUnitCode;
    }

    public function getProvinceId(): ?string
    {
        return $this->provinceId;
    }

    public function getDistrictId(): ?string
    {
        return $this->districtId;
    }

    public function getWardCode(): ?string
    {
        return $this->wardCode;
    }

    public function getProvinceName(): ?string
    {
        return $this->provinceName;
    }

    public function getWardName(): ?string
    {
        return $this->wardName;
    }

    public function hasCompleteLegacyTriple(): bool
    {
        return $this->provinceId !== null && $this->districtId !== null && $this->wardCode !== null;
    }

    public function hasNewAddressNames(): bool
    {
        return $this->provinceName !== null && $this->provinceName !== ''
            && $this->wardName !== null && $this->wardName !== '';
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'secomm_scheme' => $this->secommScheme,
            'secomm_unit_code' => $this->secommUnitCode,
            'province_id' => $this->provinceId,
            'district_id' => $this->districtId,
            'ward_code' => $this->wardCode,
            'province_name' => $this->provinceName,
            'ward_name' => $this->wardName,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $scalar = static fn (string $key): ?string => isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : null;

        return new self(
            (string) ($data['secomm_scheme'] ?? ''),
            (string) ($data['secomm_unit_code'] ?? ''),
            $scalar('province_id'),
            $scalar('district_id'),
            $scalar('ward_code'),
            $scalar('province_name'),
            $scalar('ward_name')
        );
    }
}
