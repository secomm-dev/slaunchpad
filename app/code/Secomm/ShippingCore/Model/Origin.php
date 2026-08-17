<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model;

use Secomm\ShippingCore\Api\OriginInterface;

/**
 * Immutable runtime shipping origin value object — see OriginInterface.
 * Carrier-specific data rides in $metadata under "{carrierCode}.{key}" keys.
 */
final class Origin implements OriginInterface
{
    /**
     * @param array<string, mixed> $metadata Dotted-key carrier metadata, e.g. ['ghtk.pick_address_id' => '123'].
     */
    public function __construct(
        private readonly ?string $sourceCode,
        private readonly ?string $countryId,
        private readonly ?int $regionId,
        private readonly ?string $province,
        private readonly ?string $district,
        private readonly ?string $ward,
        private readonly ?string $street,
        private readonly ?string $postcode,
        private readonly ?string $telephone,
        private readonly ?string $contactName,
        private readonly array $metadata = []
    ) {
    }

    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getRegionId(): ?int
    {
        return $this->regionId;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function getWard(): ?string
    {
        return $this->ward;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->metadata) ? $this->metadata[$key] : $default;
    }

    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }
}
