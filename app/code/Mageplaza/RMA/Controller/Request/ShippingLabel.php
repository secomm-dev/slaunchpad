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
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\RMA\Helper\Data;

/**
 * Class ShippingLabel
 * @package Mageplaza\RMA\Controller\Request
 */
class ShippingLabel extends Action
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
     * Form constructor.
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
        parent::__construct($context);

        $this->_resultPageFactory = $resultPageFactory;
        $this->_helperData = $helperData;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     * @throws NotFoundException
     */
    public function execute()
    {
        if (!$this->_helperData->isEnabled()) {
            return $this->_redirect('noroute');
        }
        if (!$requestId = $this->getRequest()->getParam('request_id')) {
            throw new NotFoundException(__('Page not found.'));
        }

        return $this->_resultPageFactory->create();
    }
}
