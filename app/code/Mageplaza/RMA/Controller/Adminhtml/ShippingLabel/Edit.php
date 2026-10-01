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

namespace Mageplaza\RMA\Controller\Adminhtml\ShippingLabel;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page as ResultPage;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Controller\Adminhtml\ShippingLabel;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;
use Mageplaza\RMA\Model\ShippingLabelFactory;

/**
 * Class Edit
 * @package Mageplaza\RMA\Controller\Adminhtml\ShippingLabel
 */
class Edit extends ShippingLabel
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
     * @param ShippingLabelFactory $shippingLabelFactory
     * @param ShippingLabelResource $shippingLabelResource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        ShippingLabelFactory $shippingLabelFactory,
        ShippingLabelResource $shippingLabelResource
    ) {
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct(
            $context,
            $coreRegistry,
            $shippingLabelFactory,
            $shippingLabelResource
        );
    }

    /**
     * @return Page|ResponseInterface|Redirect|ResultInterface|ResultPage
     */
    public function execute()
    {
        /** @var \Mageplaza\RMA\Model\ShippingLabel $shippingLabel */
        $shippingLabel = $this->initShippingLabel();
        if (!$shippingLabel) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/');

            return $resultRedirect;
        }

        $data = $this->_session->getData('mageplaza_rma_shipping_label_data', true);
        if (!empty($data)) {
            $shippingLabel->setData($data);
        }

        $this->coreRegistry->register('mageplaza_rma_shipping_label', $shippingLabel);

        /** @var Page|ResultPage $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mageplaza_RMA::shipping_label');
        $resultPage->getConfig()->getTitle()->set(__('Manage Shipping Labels'));

        $title = $shippingLabel->getId() ? $shippingLabel->getLabel() : __('New Shipping Label');
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}
