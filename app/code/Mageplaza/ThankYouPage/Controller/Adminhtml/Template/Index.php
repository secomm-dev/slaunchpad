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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Controller\Adminhtml\Template;

use Magento\Backend\Model\View\Result\Page;
use Mageplaza\ThankYouPage\Controller\Adminhtml\Template;

/**
 * Class Index
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml\Template
 */
class Index extends Template
{
    /**
     * @return Page
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->_initAction();
        $resultPage->setActiveMenu('Mageplaza_ThankYouPage::template');
        $resultPage->getConfig()->getTitle()->prepend(__('Thank You Page Rules'));

        return $resultPage;
    }
}
