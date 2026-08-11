<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\DataProvider\Mapping;

use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ListingDataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $data = [];
        foreach ($this->getCollection() as $item) {
        $data[$item->getId()] = $item->getData();
        }
        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($data),
        ];
    }
}
