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
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Mageplaza\ThankYouPage\Controller\Adminhtml\Template;
use Magento\Framework\Filter\FilterInput;
/**
 * Class Save
 * @package Mageplaza\ThankYouPage\Controller\Adminhtml\Template
 */
class Save extends Template
{
    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue('mpthankyoupage');
        if ($data) {
            $type       = $this->getRequest()->getParam('page_type');
            $templateId = $this->getRequest()->getParam('template_id');

            try {
                /** @var $model \Mageplaza\ThankYouPage\Model\Template */
                $model = $this->templateFactory->create();

                $filterValues = ['from_date' => $this->_dateFilter];
                $inputFilter  = new FilterInput($filterValues, [], $data);
                $data         = $inputFilter->getUnescaped();

                if ($templateId) {
                    $model->load($templateId);
                    if ($templateId != $model->getId()) {
                        throw new LocalizedException(__('The wrong template is specified.'));
                    }
                }

                $validateResult = $model->validateData(new DataObject($data));
                if ($validateResult !== true) {
                    foreach ($validateResult as $errorMessage) {
                        $this->messageManager->addErrorMessage($errorMessage);
                    }
                    $this->_session->setPageData($data);
                    $this->_redirect(
                        'mpthankyoupage/template/edit',
                        ['template_id' => $model->getId(), 'page_type' => $model->getPageType()]
                    );

                    return;
                }
                $rule = $this->getRequest()->getPostValue('rule');
                if ($rule) {
                    if (isset($rule['conditions'])) {
                        $data['conditions'] = $rule['conditions'];
                    }
                    unset($rule);
                }

                $data['page_type'] = $type;
                $model->loadPost($data);
                $model->save();
                $this->messageManager->addSuccess(__('You saved the rule.'));
                $this->_session->setPageData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect(
                        'mpthankyoupage/template/edit',
                        ['template_id' => $model->getId(), 'page_type' => $model->getPageType()]
                    );

                    return;
                }
                $this->_redirect('mpthankyoupage/template/*/');

                return;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                if (!empty($templateId)) {
                    $this->_redirect(
                        'mpthankyoupage/template/edit',
                        ['template_id' => $model->getId(), 'page_type' => $model->getPageType()]
                    );
                } else {
                    $this->_redirect('mpthankyoupage/template/new');
                }

                return;
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Something went wrong while saving the rule data. Please review the error log.')
                );
                $this->logger->critical($e);
                $this->_redirect('mpthankyoupage/template/edit', ['template_id' => $templateId, 'page_type' => $type]);

                return;
            }
        }
        $this->_redirect('mpthankyoupage/*/');
    }
}
