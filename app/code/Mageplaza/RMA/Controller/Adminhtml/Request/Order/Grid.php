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

namespace Mageplaza\RMA\Controller\Adminhtml\Request\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class ImageProducts
 * @package Mageplaza\MassProductActions\Controller\Adminhtml\Product\Grid
 */
class Grid extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var PageFactory
     */
    protected $_pageFactory;

    /**
     * @var LayoutFactory
     */
    protected $_layoutFactory;

    /**
     * Products constructor.
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param LayoutFactory $layoutFactory
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        LayoutFactory $layoutFactory
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_layoutFactory = $layoutFactory;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        return $this->_layoutFactory->create();
    }
}
