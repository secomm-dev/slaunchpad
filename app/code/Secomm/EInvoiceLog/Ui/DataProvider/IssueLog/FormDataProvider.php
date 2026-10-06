<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Ui\DataProvider\IssueLog;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Secomm\EInvoiceLog\Model\ResourceModel\IssueLog\CollectionFactory;

/**
 * Data provider for read-only issue log detail form.
 */
class FormDataProvider extends AbstractDataProvider
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * Load issue log row for the requested entity ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== []) {
            return $this->loadedData;
        }

        $entityId = (int) $this->request->getParam($this->requestFieldName);
        if ($entityId <= 0) {
            return $this->loadedData;
        }

        $items = $this->collection->addFieldToFilter('entity_id', $entityId)->getItems();
        foreach ($items as $item) {
            $this->loadedData[(int) $item->getId()] = $item->getData();
        }

        return $this->loadedData;
    }
}
