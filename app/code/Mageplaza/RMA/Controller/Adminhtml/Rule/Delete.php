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

namespace Mageplaza\RMA\Controller\Adminhtml\Rule;

use Exception;
use Magento\Framework\Controller\Result\Redirect;
use Mageplaza\RMA\Controller\Adminhtml\Rule;
use Mageplaza\RMA\Model\Rule as RuleModel;

/**
 * Class Delete
 * @package Mageplaza\RMA\Controller\Adminhtml\Rule
 */
class Delete extends Rule
{
    /**
     * @return Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ruleId = $this->getRequest()->getParam('id');
        if ($rule = $this->initRule()) {
            try {
                /** @var RuleModel $rule */
                $this->_ruleResource->delete($rule);
                $this->messageManager->addSuccessMessage(__('The Rule has been deleted.'));
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $resultRedirect->setPath('*/*/edit', ['id' => $ruleId]);

                return $resultRedirect;
            }
        }
        $resultRedirect->setPath('*/*/');

        return $resultRedirect;
    }
}
