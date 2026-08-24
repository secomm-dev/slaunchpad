<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Api;

/**
 * Provides the allowlisted component definitions.
 */
interface ComponentRegistryInterface
{
    /**
     * Return an enabled component definition by ID.
     *
     * @param string $componentId Stable component ID.
     */
    public function get(string $componentId): ?ComponentDefinitionInterface;

    /**
     * Return all enabled definitions in display order.
     *
     * @return ComponentDefinitionInterface[]
     */
    public function getAll(): array;
}
