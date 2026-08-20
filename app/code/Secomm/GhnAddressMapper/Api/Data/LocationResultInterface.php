<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Api\Data;

interface LocationResultInterface
{
    const PROVINCE_ID = 'province_id';
    const DISTRICT_ID = 'district_id';
    const WARD_CODE = 'ward_code';

    /**
     * @return int
     */
    public function getProvinceId(): int;

    /**
     * @return int
     */
    public function getDistrictId(): int;

    /**
     * @return string
     */
    public function getWardCode(): string;
}
