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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscUltimate\Plugin;

use Closure;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Mageplaza\Osc\Controller\Adminhtml\Field\Position;

/**
 * Class OscPosition
 * @package Mageplaza\OscUltimate\Plugin
 */
class OscPosition
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * OscPosition constructor.
     *
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;

    }

    /**
     * @return Page
     */
    public function aroundExecute(Position $subject,Closure $proceed,)
    {
        $resultPage = $this->resultPageFactory->create();
        /**
         * Set active menu item
         */
        $resultPage->setActiveMenu('Mageplaza_Osc::field_management');
        $resultPage->getConfig()->getTitle()->prepend(__('Manage Checkout Layout'));

        /**
         * Add breadcrumb item
         */
        $resultPage->addBreadcrumb(__('One Step Checkout'), __('One Step Checkout'));
        $resultPage->addBreadcrumb(__('Manage Fields'), __('Manage Fields'));

        return $resultPage;
    }
}
