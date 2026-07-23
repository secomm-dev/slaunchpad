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
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Lookbook\Model\ResourceModel\Slider as ResourceModel;
use Mageplaza\Lookbook\Model\SliderFactory;

/**
 * Class Slider
 * @package Mageplaza\Lookbook\Controller\Adminhtml
 */
abstract class Slider extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_Lookbook::slider';
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
     * @var SliderFactory
     */
    protected $sliderFactory;
    /**
     * @var ResourceModel
     */
    protected $resourceModel;
    /**
     * @var ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * Slider constructor.
     *
     * @param Action\Context $context
     * @param PageFactory $pageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param Registry $registry
     * @param SliderFactory $sliderFactory
     * @param ResourceModel $resourceModel
     */
    public function __construct(
        Action\Context $context,
        PageFactory $pageFactory,
        ForwardFactory $resultForwardFactory,
        Registry $registry,
        SliderFactory $sliderFactory,
        ResourceModel $resourceModel
    ) {
        $this->resultPageFactory = $pageFactory;
        $this->resultForwardFactory = $resultForwardFactory;
        $this->coreRegistry = $registry;
        $this->sliderFactory = $sliderFactory;
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
        $resultPage->setActiveMenu('Mageplaza_Lookbook::slider')
            ->addBreadcrumb(__('Lookbook'), __('Lookbook'))
            ->addBreadcrumb(__('Manage Sliders'), __('Manage Sliders'));

        return $resultPage;
    }

    /**
     * @return ResponseInterface|\Mageplaza\Lookbook\Model\Slider
     */
    protected function initModel()
    {
        $id = (int)$this->getRequest()->getParam('slider_id');
        /** @var \Mageplaza\Lookbook\Model\Slider $model */
        $model = $this->sliderFactory->create();
        if ($id) {
            $this->resourceModel->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This slider no longer exists.'));

                return $this->_redirect('*/*');
            }
        }

        return $model;
    }
}
