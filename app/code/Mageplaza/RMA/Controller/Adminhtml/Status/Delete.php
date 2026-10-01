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

namespace Mageplaza\RMA\Controller\Adminhtml\Status;

use Exception;
use Magento\Framework\Controller\Result\Redirect;
use Mageplaza\RMA\Controller\Adminhtml\Status;
use Mageplaza\RMA\Model\Status as StatusModel;

/**
 * Class Delete
 * @package Mageplaza\RMA\Controller\Adminhtml\Status
 */
class Delete extends Status
{
    /**
     * @return Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $statusId = $this->getRequest()->getParam('id');
        if ($status = $this->initStatus()) {
            try {
                /** @var StatusModel $status */
                $this->_statusResource->delete($status);
                $this->messageManager->addSuccessMessage(__('The Status has been deleted.'));
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $resultRedirect->setPath('*/*/edit', ['id' => $statusId]);

                return $resultRedirect;
            }
        }
        $resultRedirect->setPath('*/*/');

        return $resultRedirect;
    }
}
