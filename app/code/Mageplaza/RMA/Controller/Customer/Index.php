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

namespace Mageplaza\RMA\Controller\Customer;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Helper\Data;

/**
 * Class Index
 * @package Mageplaza\RMA\Controller\Customer
 */
class Index extends Action
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
     * Index constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $helperData
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->_helperData->isEnabled()) {
            return $this->_redirect('noroute');
        }
        /** @var Page $resultPage */
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('My RMA Requests'));

        $block = $resultPage->getLayout()->getBlock('customer.account.link.back');
        if ($block) {
            $block->setRefererUrl($this->_redirect->getRefererUrl());
        }

        return $resultPage;
    }
}
