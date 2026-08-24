<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

use Secomm\UiWidget\Api\ComponentRegistryInterface;
use Secomm\UiWidget\Api\TemplateResolverInterface;

/**
 * Resolves templates through the allowlisted component registry.
 */
class TemplateResolver implements TemplateResolverInterface
{
    /**
     * @param ComponentRegistryInterface $registry Component registry.
     */
    public function __construct(private readonly ComponentRegistryInterface $registry)
    {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $componentId): ?string
    {
        return $this->registry->get($componentId)?->getTemplate();
    }
}
