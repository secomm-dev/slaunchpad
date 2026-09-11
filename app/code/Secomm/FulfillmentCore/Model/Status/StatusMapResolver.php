<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Status;

use Secomm\FulfillmentCore\Api\Data\StatusMapInterface;
use Secomm\FulfillmentCore\Api\StatusMapResolverInterface;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap\CollectionFactory;

/**
 * Resolve active vendor raw status → normalized map for a service.
 */
class StatusMapResolver implements StatusMapResolverInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param string $serviceCode Adapter service_code
     * @param string $externalStatusCode Vendor raw status code
     */
    public function resolve(string $serviceCode, string $externalStatusCode): ?StatusMapInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('service_code', $serviceCode);
        $collection->addFieldToFilter('external_status_code', $externalStatusCode);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setPageSize(1);

        /** @var StatusMapInterface $item */
        $item = $collection->getFirstItem();
        return $item->getEntityId() ? $item : null;
    }
}
