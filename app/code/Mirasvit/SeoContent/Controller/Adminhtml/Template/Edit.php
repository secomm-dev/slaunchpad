<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\SeoContent\Controller\Adminhtml\Template;

use Magento\Framework\Controller\ResultFactory;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Controller\Adminhtml\Template;

class Edit extends Template
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $id = $this->getRequest()->getParam(TemplateInterface::ID);
        $model = $this->initModel();

        if ($id && !$model) {
            $this->messageManager->addErrorMessage(__('This template no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $this->initPage($resultPage)->getConfig()->getTitle()
            ->prepend($id ? $model->getName() : __('New template'));

        return $resultPage;
    }
}
