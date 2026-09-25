<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Exception\InputException;

/**
 * Add additional filter to collection
 */
class FilterPool
{
    /**
     * FilterPool constructor
     *
     * @param \Tiktok\Tiktok\Model\Catalog\Export\Provider\Filters\FilterInterface[] $filters
     * @throws \Magento\Framework\Exception\InputException
     */
    public function __construct(
        private readonly array $filters = []
    ) {
        foreach ($this->filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                throw new InputException(
                    __('Filter %1 doesn\'t implement FilterInterface', get_class($filter))
                );
            }
        }
    }

    /**
     * Add filters
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return void
     */
    public function execute(Collection $collection): void
    {
        foreach ($this->filters as $filter) {
            $filter->applyFilter($collection);
        }
    }
}
