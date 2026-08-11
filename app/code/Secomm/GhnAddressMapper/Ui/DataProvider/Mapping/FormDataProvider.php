<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\DataProvider\Mapping;

use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping\CollectionFactory;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Psr\Log\LoggerInterface;

class FormDataProvider extends AbstractDataProvider
{
    protected $collection;
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        protected LocationMappingRepositoryInterface $repository,
        protected RequestInterface $request,
        protected DataPersistorInterface $dataPersistor,
        protected LoggerInterface $logger,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $id = $this->request->getParam($this->getRequestFieldName());
        $this->loadedData = [];

        if ($id) {
            try {
                $mapping = $this->repository->getById((int)$id);
                $this->loadedData[$mapping->getId()] = $mapping->getData();
            } catch (\Exception $e) {
                $this->logger->error('GHN Address Mapper: FormDataProvider failed to load mapping', [
                    'id' => $id,
                    'exception' => $e
                ]);
                $this->loadedData = [];
            }
        } else {
            $data = $this->dataPersistor->get('secomm_ghn_address_mapping');
            if (!empty($data)) {
                $mapping = $this->collection->getNewEmptyItem();
                $mapping->setData($data);
                $this->loadedData[$mapping->getId()] = $mapping->getData();
                $this->dataPersistor->clear('secomm_ghn_address_mapping');
            }
        }

        return $this->loadedData;
    }
}
