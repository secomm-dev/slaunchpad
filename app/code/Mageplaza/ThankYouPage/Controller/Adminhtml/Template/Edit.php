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
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Mageplaza\ThankYouPage\Controller\Adminhtml\Template;

/**
 * Class Edit
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml\Template
 */
class Edit extends Template
{
    /**
     * Resource permission
     */
    const ADMIN_RESOURCE = 'Mageplaza_ThankYouPage::template';

    /**
     * @return Page|ResponseInterface
     */
    public function execute()
    {
        $templateId = $this->getRequest()->getParam('template_id');
        $type       = $this->getRequest()->getParam('page_type');
        $model      = $this->templateFactory->create();
        if ($templateId) {
            try {
                $model->load($templateId);
                if ($model->getPageType() != $type) {
                    $this->messageManager->addErrorMessage(__('Something went wrong.'));

                    return $this->_redirect('mpthankyoupage/*');
                }
            } catch (NoSuchEntityException $exception) {
                $this->messageManager->addErrorMessage(__('This template no longer exists.'));

                return $this->_redirect('mpthankyoupage/*');
            }
        }

        // set entered data if was error when we do save
        $data = $this->_session->getData('mpthankyoupage_template_data', true);
        if (!empty($data)) {
            $model->setData($data);
        }

        $this->coreRegistry->register('mpthankyoupage_template', $model);
        $this->coreRegistry->register('mpthankyoupage_pagetype', $type);

        /** @var Page $resultPage */
        $resultPage = $this->_initAction();
        $resultPage->getConfig()->getTitle()->prepend($templateId ? $model->getName() : __('New Rule'));

        return $resultPage;
    }
}
