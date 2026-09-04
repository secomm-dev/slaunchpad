<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

use InvalidArgumentException;
use Secomm\UiWidget\Api\ComponentDefinitionInterface;
use Secomm\UiWidget\Api\ComponentRegistryInterface;

/**
 * In-memory registry of explicitly injected component definitions.
 */
class Registry implements ComponentRegistryInterface
{
    /** @var array<string, ComponentDefinitionInterface> */
    private array $definitions = [];

    /**
     * @param ComponentDefinitionInterface[] $definitions
     */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $definition) {
            if (!$definition instanceof ComponentDefinitionInterface) {
                throw new InvalidArgumentException(
                    'Every component definition must implement ComponentDefinitionInterface.'
                );
            }
            if (isset($this->definitions[$definition->getId()])) {
                throw new InvalidArgumentException(sprintf('Duplicate component ID: %s.', $definition->getId()));
            }
            $this->definitions[$definition->getId()] = $definition;
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $componentId): ?ComponentDefinitionInterface
    {
        $definition = $this->definitions[$componentId] ?? null;

        return $definition?->isEnabled() ? $definition : null;
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        $definitions = array_filter(
            $this->definitions,
            static fn (ComponentDefinitionInterface $definition): bool => $definition->isEnabled()
        );
        usort(
            $definitions,
            static fn (ComponentDefinitionInterface $left, ComponentDefinitionInterface $right): int =>
                [$left->getSortOrder(), $left->getLabel(), $left->getId()]
                <=> [$right->getSortOrder(), $right->getLabel(), $right->getId()]
        );

        return array_values($definitions);
    }
}
