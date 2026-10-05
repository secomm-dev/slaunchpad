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

namespace Mageplaza\RMA\Controller\Adminhtml\Request;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page as ResultPage;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Controller\Adminhtml\Request;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;

/**
 * Class Edit
 * @package Mageplaza\RMA\Controller\Adminhtml\Request
 */
class Edit extends Request
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
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        RequestFactory $requestFactory,
        RequestResource $requestResource
    ) {
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct(
            $context,
            $coreRegistry,
            $requestFactory,
            $requestResource
        );
    }

    /**
     * @return Page|ResponseInterface|Redirect|ResultInterface|ResultPage
     */
    public function execute()
    {
        /** @var \Mageplaza\RMA\Model\Request $request */
        $request = $this->initRequest();
        if (!$request) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/');

            return $resultRedirect;
        }

        $data = $this->_session->getData('mageplaza_rma_request_data', true);
        if (!empty($data)) {
            $request->setData($data);
        }

        $this->coreRegistry->register('mageplaza_rma_request', $request);

        /** @var Page|ResultPage $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mageplaza_RMA::request');
        $resultPage->getConfig()->getTitle()->set(__('Manage Requests'));

        $title = $request->getId() ? $request->getIncrementId() : __('New Request');
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}
