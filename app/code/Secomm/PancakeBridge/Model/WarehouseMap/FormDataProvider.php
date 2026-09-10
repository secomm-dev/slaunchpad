<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Model\WarehouseMap;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentWarehouseMap\CollectionFactory;
use Secomm\PancakeBridge\Model\Order\PancakeOrderExporter;

class FormDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>> */
    private array $loadedData = [];

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->collection->addFieldToFilter('service_code', PancakeOrderExporter::SERVICE_CODE);
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== []) {
            return $this->loadedData;
        }

        foreach ($this->collection->getItems() as $item) {
            $this->loadedData[(int) $item->getEntityId()] = $item->getData();
        }

        $persisted = $this->dataPersistor->get('pancake_warehouse_map');
        if (!empty($persisted)) {
            $id = (int) ($persisted['entity_id'] ?? 0);
            $this->loadedData[$id] = $persisted;
            $this->dataPersistor->clear('pancake_warehouse_map');
        }

        return $this->loadedData;
    }
}
