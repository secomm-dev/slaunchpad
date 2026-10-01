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

use Exception;
use Magento\Framework\Controller\Result\Redirect;
use Mageplaza\RMA\Controller\Adminhtml\ShippingLabel;

/**
 * Class Delete
 * @package Mageplaza\RMA\Controller\Adminhtml\ShippingLabel
 */
class Delete extends ShippingLabel
{
    /**
     * @return Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $shippingLabelId = $this->getRequest()->getParam('id');
        if ($shippingLabel = $this->initShippingLabel()) {
            try {
                $this->_shippingLabelResource->delete($shippingLabel);
                $this->messageManager->addSuccessMessage(__('The Shipping Label has been deleted.'));
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $resultRedirect->setPath('*/*/edit', ['id' => $shippingLabelId]);

                return $resultRedirect;
            }
        }
        $resultRedirect->setPath('*/*/');

        return $resultRedirect;
    }
}
