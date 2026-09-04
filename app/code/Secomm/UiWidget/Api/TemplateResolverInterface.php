<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Api;

/**
 * Resolves only templates declared by registered component definitions.
 */
interface TemplateResolverInterface
{
    /**
     * Return the registered template alias for a component.
     *
     * @param string $componentId Stable component ID.
     */
    public function resolve(string $componentId): ?string;
}
