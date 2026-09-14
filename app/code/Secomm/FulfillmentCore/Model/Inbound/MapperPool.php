<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Inbound;

use Secomm\FulfillmentCore\Api\FulfillmentStatusMapperInterface;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;

/**
 * DI-assembled map of service_code => FulfillmentStatusMapperInterface.
 */
class MapperPool
{
    /**
     * @param FulfillmentStatusMapperInterface[] $mappers
     */
    public function __construct(
        private readonly array $mappers = []
    ) {
    }

    /**
     * @return FulfillmentStatusMapperInterface[] keyed by service_code
     */
    public function getMappers(): array
    {
        $map = [];
        foreach ($this->mappers as $mapper) {
            if (!$mapper instanceof FulfillmentStatusMapperInterface) {
                continue;
            }
            $map[$mapper->getServiceCode()] = $mapper;
        }

        return $map;
    }

    public function getMapper(string $serviceCode): ?FulfillmentStatusMapperInterface
    {
        return $this->getMappers()[$serviceCode] ?? null;
    }

    /**
     * Map raw vendor status; returns UNKNOWN when no mapper or unmapped code.
     */
    public function map(string $serviceCode, string $rawStatus): string
    {
        $mapper = $this->getMapper($serviceCode);
        if ($mapper === null) {
            return NormalizedFulfillmentStatus::UNKNOWN;
        }

        $normalized = $mapper->map($rawStatus);
        return $normalized !== '' ? $normalized : NormalizedFulfillmentStatus::UNKNOWN;
    }
}
