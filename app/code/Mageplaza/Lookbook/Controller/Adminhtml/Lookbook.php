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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Lookbook\Model\LookbookFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Lookbook as ResourceModel;

/**
 * Class Lookbook
 * @package Mageplaza\Lookbook\Controller\Adminhtml
 */
abstract class Lookbook extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Lookbook::lookbook';
    /**
     * Page result factory
     *
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Page factory
     *
     * @var Page
     */
    protected $resultPage;
    /**
     * @var Registry
     */
    protected $coreRegistry;
    /**
     * @var LookbookFactory
     */
    protected $lookbookFactory;
    /**
     * @var ResourceModel
     */
    protected $resourceModel;
    /**
     * @var ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * Lookbook constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param LookbookFactory $lookbookFactory
     * @param ResourceModel $resourceModel
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        LookbookFactory $lookbookFactory,
        ResourceModel $resourceModel
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultForwardFactory = $resultForwardFactory;
        $this->lookbookFactory = $lookbookFactory;
        $this->resourceModel = $resourceModel;

        parent::__construct($context);
    }

    /**
     * Init layout, menu and breadcrumb
     *
     * @return Page
     */
    protected function _initPage()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mageplaza_Lookbook::lookbook')
            ->addBreadcrumb(__('Lookbook'), __('Lookbook'))
            ->addBreadcrumb(__('Manage Lookbooks'), __('Manage Lookbooks'));

        return $resultPage;
    }

    /**
     * @return ResponseInterface|\Mageplaza\Lookbook\Model\Lookbook
     */
    protected function _initLookbook()
    {
        $id = (int)$this->getRequest()->getParam('lookbook_id');
        /** @var \Mageplaza\Lookbook\Model\Lookbook $model */
        $model = $this->lookbookFactory->create();
        if ($id) {
            $this->resourceModel->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This Lookbook no longer exists.'));

                return $this->_redirect('*/*');
            }
        }

        return $model;
    }
}
