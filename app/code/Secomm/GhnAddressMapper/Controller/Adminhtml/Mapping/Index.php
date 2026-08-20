<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Secomm_GhnAddressMapper::mapping');
        $resultPage->getConfig()->getTitle()->prepend(__('GHN Address Mapping'));
        return $resultPage;
    }
}
