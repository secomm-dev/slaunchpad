<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\Ghtk\Api\Data\GhtkAddressMapInterface;
use Secomm\Ghtk\Model\ResourceModel\GhtkAddressMap as GhtkAddressMapResource;

class GhtkAddressMap extends AbstractModel implements GhtkAddressMapInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_ghtk_address_map';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(GhtkAddressMapResource::class);
    }

    public function getMapId(): ?int
    {
        return $this->getData(self::MAP_ID) !== null
            ? (int) $this->getData(self::MAP_ID)
            : null;
    }

    public function setMapId(?int $mapId): GhtkAddressMapInterface
    {
        return $this->setData(self::MAP_ID, $mapId);
    }

    public function getCountryId(): string
    {
        return (string) $this->getData(self::COUNTRY_ID);
    }

    public function setCountryId(string $countryId): GhtkAddressMapInterface
    {
        return $this->setData(self::COUNTRY_ID, $countryId);
    }

    public function getRegionId(): int
    {
        return (int) $this->getData(self::REGION_ID);
    }

    public function setRegionId(int $regionId): GhtkAddressMapInterface
    {
        return $this->setData(self::REGION_ID, $regionId);
    }

    public function getWardId(): int
    {
        return (int) $this->getData(self::WARD_ID);
    }

    public function setWardId(int $wardId): GhtkAddressMapInterface
    {
        return $this->setData(self::WARD_ID, $wardId);
    }

    public function getSourceProvinceName(): ?string
    {
        return $this->getData(self::SOURCE_PROVINCE_NAME);
    }

    public function setSourceProvinceName(?string $name): GhtkAddressMapInterface
    {
        return $this->setData(self::SOURCE_PROVINCE_NAME, $name);
    }

    public function getSourceWardName(): ?string
    {
        return $this->getData(self::SOURCE_WARD_NAME);
    }

    public function setSourceWardName(?string $name): GhtkAddressMapInterface
    {
        return $this->setData(self::SOURCE_WARD_NAME, $name);
    }

    public function getGhtkProvince(): string
    {
        return (string) $this->getData(self::GHTK_PROVINCE);
    }

    public function setGhtkProvince(string $province): GhtkAddressMapInterface
    {
        return $this->setData(self::GHTK_PROVINCE, $province);
    }

    public function getGhtkDistrict(): ?string
    {
        return $this->getData(self::GHTK_DISTRICT);
    }

    public function setGhtkDistrict(?string $district): GhtkAddressMapInterface
    {
        return $this->setData(self::GHTK_DISTRICT, $district);
    }

    public function getGhtkWard(): string
    {
        return (string) $this->getData(self::GHTK_WARD);
    }

    public function setGhtkWard(string $ward): GhtkAddressMapInterface
    {
        return $this->setData(self::GHTK_WARD, $ward);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): GhtkAddressMapInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(?string $createdAt): GhtkAddressMapInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt): GhtkAddressMapInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
