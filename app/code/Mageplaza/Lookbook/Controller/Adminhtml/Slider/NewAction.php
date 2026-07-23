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

namespace Mageplaza\Lookbook\Controller\Adminhtml\Slider;

use Magento\Backend\Model\View\Result\Forward;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\Lookbook\Controller\Adminhtml\Slider;

/**
 * Class NewAction
 * @package Mageplaza\Lookbook\Controller\Adminhtml\Slider
 */
class NewAction extends Slider
{

    /**
     * @return Forward|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        return $this->resultForwardFactory->create()->forward('edit');
    }
}
