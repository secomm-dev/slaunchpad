<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected LocationMappingRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('entity_id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id) {
            try {
                $this->repository->deleteById((int)$id);
                $this->messageManager->addSuccessMessage(__('Mapping deleted successfully.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Could not delete mapping. Please try again.'));
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}
