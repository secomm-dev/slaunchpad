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

use Mageplaza\ThankYouPage\Controller\Adminhtml\Template;

/**
 * Class NewAction
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml\Template
 */
class NewAction extends Template
{
    /**
     * @return mixed
     */
    public function execute()
    {
        return $this->resultForwardFactory->create()->forward('edit');
    }
}
