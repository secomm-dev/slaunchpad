<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\StatusMap;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap\CollectionFactory;
use Secomm\Pancake\Model\Order\PancakeOrderExporter;

class ListingDataProvider extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->collection->addFieldToFilter('service_code', PancakeOrderExporter::SERVICE_CODE);
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
}
