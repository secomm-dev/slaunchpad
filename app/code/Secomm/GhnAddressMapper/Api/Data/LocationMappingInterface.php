<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Api\Data;

interface LocationMappingInterface
{
    const ENTITY_ID = 'entity_id';
    const COUNTRY_ID = 'country_id';
    const REGION_ID = 'region_id';
    const REGION_NAME = 'region_name';
    const CITY_ID = 'city_id';
    const CITY_NAME = 'city_name';
    const GHN_PROVINCE_ID = 'ghn_province_id';
    const GHN_PROVINCE_NAME = 'ghn_province_name';
    const GHN_DISTRICT_ID = 'ghn_district_id';
    const GHN_DISTRICT_NAME = 'ghn_district_name';
    const GHN_WARD_CODE = 'ghn_ward_code';
    const GHN_WARD_NAME = 'ghn_ward_name';
    const PRIORITY = 'priority';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return self
     */
    public function setEntityId($entityId): self;

    /**
     * @return string
     */
    public function getCountryId(): string;

    /**
     * @param string $countryId
     * @return self
     */
    public function setCountryId(string $countryId): self;

    /**
     * @return int
     */
    public function getRegionId(): int;

    /**
     * @param int $regionId
     * @return self
     */
    public function setRegionId(int $regionId): self;

    /**
     * @return string|null
     */
    public function getRegionName(): ?string;

    /**
     * @param string|null $regionName
     * @return self
     */
    public function setRegionName(?string $regionName): self;

    /**
     * @return int|null
     */
    public function getCityId(): ?int;

    /**
     * @param int|null $cityId
     * @return self
     */
    public function setCityId(?int $cityId): self;

    /**
     * @return string|null
     */
    public function getCityName(): ?string;

    /**
     * @param string|null $cityName
     * @return self
     */
    public function setCityName(?string $cityName): self;

    /**
     * @return int
     */
    public function getGhnProvinceId(): int;

    /**
     * @param int $provinceId
     * @return self
     */
    public function setGhnProvinceId(int $provinceId): self;

    /**
     * @return string|null
     */
    public function getGhnProvinceName(): ?string;

    /**
     * @param string|null $provinceName
     * @return self
     */
    public function setGhnProvinceName(?string $provinceName): self;

    /**
     * @return int
     */
    public function getGhnDistrictId(): int;

    /**
     * @param int $districtId
     * @return self
     */
    public function setGhnDistrictId(int $districtId): self;

    /**
     * @return string|null
     */
    public function getGhnDistrictName(): ?string;

    /**
     * @param string|null $districtName
     * @return self
     */
    public function setGhnDistrictName(?string $districtName): self;

    /**
     * @return string
     */
    public function getGhnWardCode(): string;

    /**
     * @param string $wardCode
     * @return self
     */
    public function setGhnWardCode(string $wardCode): self;

    /**
     * @return string|null
     */
    public function getGhnWardName(): ?string;

    /**
     * @param string|null $wardName
     * @return self
     */
    public function setGhnWardName(?string $wardName): self;

    /**
     * @return int
     */
    public function getStatus(): int;

    /**
     * @param int $status
     * @return self
     */
    public function setStatus(int $status): self;

    /**
     * @return int
     */
    public function getPriority(): int;

    /**
     * @param int $priority
     * @return self
     */
    public function setPriority(int $priority): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
