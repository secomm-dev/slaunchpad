<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\UiWidget\Api\ComponentRegistryInterface;

/**
 * Supplies enabled component definitions to the widget selector.
 */
class Component implements OptionSourceInterface
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
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Please Select --')]];
        foreach ($this->registry->getAll() as $definition) {
            $options[] = [
                'value' => $definition->getId(),
                'label' => __($definition->getLabel()),
            ];
        }

        return $options;
    }
}
