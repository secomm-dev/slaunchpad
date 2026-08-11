<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model\Data;

use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterface;
use Magento\Framework\Model\AbstractModel;

class LocationMappingData extends AbstractModel implements LocationMappingInterface
{
    protected function _construct()
    {
        $this->_init(\Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping::class);
    }

    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) ? (int)$this->getData(self::ENTITY_ID) : null;
    }

    public function setEntityId($entityId): LocationMappingInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getCountryId(): string
    {
        $countryId = (string)$this->getData(self::COUNTRY_ID);
        return $countryId !== '' ? $countryId : 'VN';
    }

    public function setCountryId(string $countryId): LocationMappingInterface
    {
        return $this->setData(self::COUNTRY_ID, $countryId);
    }

    public function getRegionId(): int
    {
        return (int)$this->getData(self::REGION_ID);
    }

    public function setRegionId(int $regionId): LocationMappingInterface
    {
        return $this->setData(self::REGION_ID, $regionId);
    }

    public function getRegionName(): ?string
    {
        return $this->getData(self::REGION_NAME);
    }

    public function setRegionName(?string $regionName): LocationMappingInterface
    {
        return $this->setData(self::REGION_NAME, $regionName);
    }

    public function getCityId(): ?int
    {
        return $this->getData(self::CITY_ID) ? (int)$this->getData(self::CITY_ID) : null;
    }

    public function setCityId(?int $cityId): LocationMappingInterface
    {
        return $this->setData(self::CITY_ID, $cityId);
    }

    public function getCityName(): ?string
    {
        return $this->getData(self::CITY_NAME);
    }

    public function setCityName(?string $cityName): LocationMappingInterface
    {
        return $this->setData(self::CITY_NAME, $cityName);
    }

    public function getGhnProvinceId(): int
    {
        return (int)$this->getData(self::GHN_PROVINCE_ID);
    }

    public function setGhnProvinceId(int $provinceId): LocationMappingInterface
    {
        return $this->setData(self::GHN_PROVINCE_ID, $provinceId);
    }

    public function getGhnProvinceName(): ?string
    {
        return $this->getData(self::GHN_PROVINCE_NAME);
    }

    public function setGhnProvinceName(?string $provinceName): LocationMappingInterface
    {
        return $this->setData(self::GHN_PROVINCE_NAME, $provinceName);
    }

    public function getGhnDistrictId(): int
    {
        return (int)$this->getData(self::GHN_DISTRICT_ID);
    }

    public function setGhnDistrictId(int $districtId): LocationMappingInterface
    {
        return $this->setData(self::GHN_DISTRICT_ID, $districtId);
    }

    public function getGhnDistrictName(): ?string
    {
        return $this->getData(self::GHN_DISTRICT_NAME);
    }

    public function setGhnDistrictName(?string $districtName): LocationMappingInterface
    {
        return $this->setData(self::GHN_DISTRICT_NAME, $districtName);
    }

    public function getGhnWardCode(): string
    {
        return (string)$this->getData(self::GHN_WARD_CODE);
    }

    public function setGhnWardCode(string $wardCode): LocationMappingInterface
    {
        return $this->setData(self::GHN_WARD_CODE, $wardCode);
    }

    public function getGhnWardName(): ?string
    {
        return $this->getData(self::GHN_WARD_NAME);
    }

    public function setGhnWardName(?string $wardName): LocationMappingInterface
    {
        return $this->setData(self::GHN_WARD_NAME, $wardName);
    }

    public function getStatus(): int
    {
        return (int)$this->getData(self::STATUS);
    }

    public function setStatus(int $status): LocationMappingInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}
