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

namespace Mageplaza\RMA\Controller\Request;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Helper\Data;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Class Form
 * @package Mageplaza\RMA\Controller\Request
 */
class Form extends Action
{
    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var Data
     */
    protected $_helperData;

    /**
     * @var CustomerSession
     */
    protected $_customerSession;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Data $helperData
     * @param CustomerSession $customerSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $helperData,
        CustomerSession $customerSession
    ) {
        parent::__construct($context);

        $this->_resultPageFactory = $resultPageFactory;
        $this->_helperData = $helperData;
        $this->_customerSession      = $customerSession;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->_helperData->isEnabled()) {
            return $this->_redirect('noroute');
        }

        if($this->_customerSession->isLoggedIn())
        {
            return $this->_redirect('mprma/customer/');
        }

        $resultPage = $this->_resultPageFactory->create();
        /** @var Page $resultPage */
        $resultPage->getConfig()->getTitle()->set(__('RMA Request'));

        return $resultPage;
    }
}
