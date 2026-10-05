<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Controller\Adminhtml\Status;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page as ResultPage;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Controller\Adminhtml\Status;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;
use Mageplaza\RMA\Model\StatusFactory;

/**
 * Class Edit
 * @package Mageplaza\RMA\Controller\Adminhtml\Status
 */
class Edit extends Status
{
    /**
     * Page factory
     *
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * Edit constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param StatusFactory $statusFactory
     * @param StatusResource $statusResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        StatusFactory $statusFactory,
        StatusResource $statusResource,
        HelperData $helperData
    ) {
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct(
            $context,
            $coreRegistry,
            $statusFactory,
            $statusResource,
            $helperData
        );
    }

    /**
     * @return Page|ResponseInterface|Redirect|ResultInterface|ResultPage
     */
    public function execute()
    {
        /** @var \Mageplaza\RMA\Model\Status $status */
        $status = $this->initStatus();

        if (!$status) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/');

            return $resultRedirect;
        }

        $data = $this->_session->getData('mageplaza_rma_status_data', true);

        if (!empty($data)) {
            $status->setData($data);
        }

        $this->coreRegistry->register('mageplaza_rma_status', $status);

        /** @var Page|ResultPage $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mageplaza_RMA::status');
        $resultPage->getConfig()->getTitle()->set(__('Manage Statues'));

        $title = $status->getId() ? $status->getName() : __('New Status');
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}
