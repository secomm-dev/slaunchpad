<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Warehouse;

use Secomm\FulfillmentCore\Api\Data\WarehouseMapInterface;
use Secomm\FulfillmentCore\Api\WarehouseMapResolverInterface;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap\CollectionFactory;

/**
 * Resolve active MSI → external warehouse map for a service.
 */
class WarehouseMapResolver implements WarehouseMapResolverInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param string $serviceCode Adapter service_code
     * @param string $magentoSourceCode MSI inventory_source.source_code
     */
    public function resolve(string $serviceCode, string $magentoSourceCode): ?WarehouseMapInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('service_code', $serviceCode);
        $collection->addFieldToFilter('magento_source_code', $magentoSourceCode);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setPageSize(1);

        /** @var WarehouseMapInterface $item */
        $item = $collection->getFirstItem();
        return $item->getEntityId() ? $item : null;
    }
}
