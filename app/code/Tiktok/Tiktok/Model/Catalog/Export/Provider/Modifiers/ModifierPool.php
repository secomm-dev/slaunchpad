<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers;

use Magento\Framework\Exception\InputException;
use Tiktok\Tiktok\Model\Catalog\Export\Provider\Modifiers\ModifierInterface;

/**
 * Add/Update product data before sending to service
 */
class ModifierPool
{
    /**
     * ModifierPool constructor
     *
     * @param array $pool
     * @throws \Magento\Framework\Exception\InputException
     */
    public function __construct(private readonly array $pool = [])
    {
        foreach ($pool as $processor) {
            if (!$processor instanceof ModifierInterface) {
                throw new InputException(
                    __('Processor %1 doesn\'t implement ModifierInterface', get_class($processor))
                );
            }
        }
    }

    /**
     * Add/Update information
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return void
     */
    public function execute($collection): void
    {
        foreach ($collection as $item) {
            foreach ($this->pool as $processor) {
                $processor->modify($item);
            }
        }
    }
}
