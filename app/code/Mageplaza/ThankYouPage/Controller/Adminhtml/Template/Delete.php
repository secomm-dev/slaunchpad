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

use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\ThankYouPage\Controller\Adminhtml\Template;
use Mageplaza\ThankYouPage\Model\TemplateFactory;

/**
 * Class Delete
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml\Template
 */
class Delete extends Template
{
    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $templateId     = $this->getRequest()->getParam('template_id');
        $pagetype       = $this->getRequest()->getParam('page_type');
        try {
            /** @var TemplateFactory $template */
            $this->templateFactory->create()
                ->load($templateId)
                ->delete();
            $this->messageManager->addSuccess(__('The template has been deleted.'));
        } catch (Exception $e) {
            // display error message
            $this->messageManager->addErrorMessage($e->getMessage());
            // go back to edit form
            $resultRedirect->setPath('mpthankyoupage/*/edit', ['template_id' => $templateId, 'page_type' => $pagetype]);

            return $resultRedirect;
        }

        $resultRedirect->setPath('mpthankyoupage/*/');

        return $resultRedirect;
    }
}
