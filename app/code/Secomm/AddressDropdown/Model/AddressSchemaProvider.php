<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model;

use Secomm\AddressDropdown\Api\AddressSchemaProviderInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — render order of the address schema: levels sorted ascending by
 * sort_order (PHP >= 8.0 usort is stable, so declaration order breaks ties).
 */
class AddressSchemaProvider implements AddressSchemaProviderInterface
{
    public function __construct(
        private readonly ProfilePool $profilePool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSchema(string $profileCode): array
    {
        $levels = $this->profilePool->getProfile($profileCode)->getLevels();

        usort($levels, static function (SchemaLevelInterface $a, SchemaLevelInterface $b): int {
            return $a->getSortOrder() <=> $b->getSortOrder();
        });

        return $levels;
    }
}
