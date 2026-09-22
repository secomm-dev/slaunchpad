<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\Ghtk\Api\Data\GhtkAddressOverrideInterface;
use Secomm\Ghtk\Model\ResourceModel\GhtkAddressOverride as GhtkAddressOverrideResource;

class GhtkAddressOverride extends AbstractModel implements GhtkAddressOverrideInterface
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
        $this->_init(GhtkAddressOverrideResource::class);
    }

    public function getMapId(): ?int
    {
        return $this->getData(self::MAP_ID) !== null
            ? (int) $this->getData(self::MAP_ID)
            : null;
    }

    public function setMapId(?int $mapId): GhtkAddressOverrideInterface
    {
        return $this->setData(self::MAP_ID, $mapId);
    }

    public function getSchemeCode(): string
    {
        return (string) $this->getData(self::SCHEME_CODE);
    }

    public function setSchemeCode(string $schemeCode): GhtkAddressOverrideInterface
    {
        return $this->setData(self::SCHEME_CODE, $schemeCode);
    }

    public function getProvinceCode(): string
    {
        return (string) $this->getData(self::PROVINCE_CODE);
    }

    public function setProvinceCode(string $provinceCode): GhtkAddressOverrideInterface
    {
        return $this->setData(self::PROVINCE_CODE, $provinceCode);
    }

    public function getWardCode(): string
    {
        return (string) $this->getData(self::WARD_CODE);
    }

    public function setWardCode(string $wardCode): GhtkAddressOverrideInterface
    {
        return $this->setData(self::WARD_CODE, $wardCode);
    }

    public function getGhtkProvince(): ?string
    {
        $value = $this->getData(self::GHTK_PROVINCE);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setGhtkProvince(?string $province): GhtkAddressOverrideInterface
    {
        return $this->setData(self::GHTK_PROVINCE, $province);
    }

    public function getGhtkDistrict(): ?string
    {
        $value = $this->getData(self::GHTK_DISTRICT);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setGhtkDistrict(?string $district): GhtkAddressOverrideInterface
    {
        return $this->setData(self::GHTK_DISTRICT, $district);
    }

    public function getGhtkWard(): ?string
    {
        $value = $this->getData(self::GHTK_WARD);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setGhtkWard(?string $ward): GhtkAddressOverrideInterface
    {
        return $this->setData(self::GHTK_WARD, $ward);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): GhtkAddressOverrideInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    public function getNote(): ?string
    {
        $value = $this->getData(self::NOTE);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setNote(?string $note): GhtkAddressOverrideInterface
    {
        return $this->setData(self::NOTE, $note);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value !== null ? (string) $value : null;
    }

    public function setCreatedAt(?string $createdAt): GhtkAddressOverrideInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);

        return $value !== null ? (string) $value : null;
    }

    public function setUpdatedAt(?string $updatedAt): GhtkAddressOverrideInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
