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



namespace Mirasvit\SeoContent\Controller\Adminhtml\Rewrite;

use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Controller\Adminhtml\Rewrite;

class Save extends Rewrite
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $id             = $this->getRequest()->getParam(RewriteInterface::ID);
        $resultRedirect = $this->resultRedirectFactory->create();

        $rawData = $this->getRequest()->getParams();

        if (!is_array($rawData) || empty($rawData)) {
            $resultRedirect->setPath('*/*/');
            $this->messageManager->addErrorMessage(__('No data to save.'));

            return $resultRedirect;
        }

        $data           = $rawData;
        $model          = $this->initModel();
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id && !$model) {
            $this->messageManager->addErrorMessage(__('This rewrite no longer exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        if (!isset($data[RewriteInterface::DESCRIPTION_POSITION])) {
            $data[RewriteInterface::DESCRIPTION_POSITION] = RewriteInterface::DESCRIPTION_POSITION_DISABLED;
        }

        if (!isset($data[RewriteInterface::META_ROBOTS])) {
            $data[RewriteInterface::META_ROBOTS] = '-';
        }

        if (!isset($data[RewriteInterface::ADD_TO_SITEMAP])) {
            $data[RewriteInterface::ADD_TO_SITEMAP] = 0;
        }

        $data = $this->contentService->escapeJS($data);

        $model->setUrl(trim((string)($data[RewriteInterface::URL] ?? '')))
            ->setIsActive((bool)($data[RewriteInterface::IS_ACTIVE] ?? false))
            ->setSortOrder($data[RewriteInterface::SORT_ORDER] ?? null)
            ->setTitle($data[RewriteInterface::TITLE] ?? null)
            ->setUseInBreadcrumbs($data[RewriteInterface::USE_IN_BREADCRUMBS] ?? null)
            ->setMetaTitle($data[RewriteInterface::META_TITLE] ?? null)
            ->setMetaKeywords($data[RewriteInterface::META_KEYWORDS] ?? null)
            ->setMetaDescription($data[RewriteInterface::META_DESCRIPTION] ?? null)
            ->setDescription($data[RewriteInterface::DESCRIPTION] ?? null)
            ->setDescriptionPosition($data[RewriteInterface::DESCRIPTION_POSITION] ?? null)
            ->setDescriptionTemplate($data[RewriteInterface::DESCRIPTION_TEMPLATE] ?? null)
            ->setMetaRobots($data[RewriteInterface::META_ROBOTS] ?? null)
            ->setAddToSitemap($data[RewriteInterface::ADD_TO_SITEMAP] ?? null)
            ->setStoreIds($data[RewriteInterface::STORE_IDS] ?? []);

        try {
            $model = $this->rewriteRepository->save($model);

            $this->messageManager->addSuccessMessage(__('Rewrite was saved.'));

            if ($this->getRequest()->getParam('back') == 'edit') {
                return $resultRedirect->setPath('*/*/edit', [RewriteInterface::ID => $model->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $resultRedirect->setPath('*/*/edit', [RewriteInterface::ID => $id]);
        }
    }
}
