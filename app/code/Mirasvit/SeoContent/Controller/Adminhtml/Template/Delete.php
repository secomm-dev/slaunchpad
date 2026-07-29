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

use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Controller\Adminhtml\Template;

class Delete extends Template
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam(TemplateInterface::ID);

        if ($id) {
            try {
                /** @var TemplateInterface $model */
                $model = $this->templateRepository->get($id);
                $this->templateRepository->delete($model);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            $this->messageManager->addSuccessMessage(
                __('Template was removed')
            );
        } else {
            $this->messageManager->addErrorMessage(__('Please select template'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
