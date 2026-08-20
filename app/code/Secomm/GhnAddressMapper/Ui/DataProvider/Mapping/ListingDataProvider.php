<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\DataProvider\Mapping;

use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ListingDataProvider extends AbstractDataProvider
{
    /**
     * Fields loaded from DB — matches columns declared in the listing UI component.
     *
     * @var string[]
     */
    private const LISTED_FIELDS = [
        'entity_id',
        'region_id',
        'city_id',
        'city_name',
        'ghn_province_id',
        'ghn_province_name',
        'ghn_district_id',
        'ghn_district_name',
        'ghn_ward_code',
        'ghn_ward_name',
        'status',
        'priority',
        'created_at',
        'updated_at',
    ];

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

        $this->getCollection()->addFieldToSelect(self::LISTED_FIELDS);

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
