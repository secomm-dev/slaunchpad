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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Controller\Adminhtml\ManageRules;

use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\ExtraFee\Controller\Adminhtml\AbstractManageRules;
use Mageplaza\ExtraFee\Model\Rule;

/**
 * Class Delete
 * @package Mageplaza\ExtraFee\Controller\Adminhtml\ManageRules
 */
class Delete extends AbstractManageRules
{
    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        /** @var Rule $rule */
        $rule = $this->initRule();
        if ($rule->getId()) {
            try {
                $rule->delete();
                $this->messageManager->addSuccessMessage(__('The Rule has been deleted.'));
                $resultRedirect->setPath('mpextrafee/*/');

                return $resultRedirect;
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                // go back to edit form
                $resultRedirect->setPath('mpextrafee/*/edit', ['rule_id' => $rule->getId()]);

                return $resultRedirect;
            }
        }
        // display error message
        $this->messageManager->addErrorMessage(__('The Rule to delete was not found.'));

        $resultRedirect->setPath('mpextrafee/*/');

        return $resultRedirect;
    }
}
