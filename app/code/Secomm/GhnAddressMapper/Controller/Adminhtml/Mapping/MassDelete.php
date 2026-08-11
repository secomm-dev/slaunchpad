<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping\CollectionFactory;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected Filter $filter,
        protected CollectionFactory $collectionFactory,
        protected LocationMappingRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;

        foreach ($collection as $mapping) {
            try {
                $this->repository->delete($mapping);
                $count++;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Could not delete mapping with ID %1.', $mapping->getEntityId())
                );
            }
        }

        if ($count > 0) {
            $this->messageManager->addSuccessMessage(__('A total of %1 mapping(s) have been deleted.', $count));
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/');
    }
}
