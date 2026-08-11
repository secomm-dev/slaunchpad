<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Api;

use Secomm\GhnAddressMapper\Api\Data\LocationResultInterface;

interface LocationResolverInterface
{
    /**
     * Resolve GHN location codes from Magento Region ID and City ID (locale-independent)
     *
     * @param int $regionId
     * @param int $cityId
     * @return LocationResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resolve(int $regionId, int $cityId): LocationResultInterface;

    /**
     * Resolve GHN location codes from Magento Region ID and a City name.
     *
     * Convenience facade for callers that only hold the city name string (e.g. the
     * value stored on a quote/order address). The resolver converts the name to the
     * locale-independent city_id internally (cached) then delegates to resolve().
     *
     * @param int $regionId
     * @param string $cityName
     * @return LocationResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resolveByName(int $regionId, string $cityName): LocationResultInterface;
}
