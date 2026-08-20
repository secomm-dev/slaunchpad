<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Api\Data;

interface LocationMappingSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * @return \Secomm\GhnAddressMapper\Api\Data\LocationMappingInterface[]
     */
    public function getItems();

    /**
     * @param \Secomm\GhnAddressMapper\Api\Data\LocationMappingInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
