<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Model\Data;

use Secomm\GhnAddressMapper\Api\Data\LocationResultInterface;
use Magento\Framework\DataObject;

class LocationResult extends DataObject implements LocationResultInterface
{
    public function getProvinceId(): int
    {
        return (int)$this->getData(self::PROVINCE_ID);
    }

    public function getDistrictId(): int
    {
        return (int)$this->getData(self::DISTRICT_ID);
    }

    public function getWardCode(): string
    {
        return (string)$this->getData(self::WARD_CODE);
    }
}
