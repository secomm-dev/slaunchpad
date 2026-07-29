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



namespace Mirasvit\SeoContent\Model\Toolbar;

use Magento\Framework\DataObject;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoContent\Api\Data\ContentInterface;
use Mirasvit\SeoContent\Service\ContentService;
use Mirasvit\SeoToolbar\Api\Service\DataProviderInterface;

class DataProvider implements DataProviderInterface
{
    /**
     * @var ContentService
     */
    private $contentService;

    /**
     * @var StateServiceInterface
     */
    private $stateService;

    /**
     * DataProvider constructor.
     * @param ContentService $contentService
     * @param StateServiceInterface $stateService
     */
    public function __construct(
        ContentService $contentService,
        StateServiceInterface $stateService
    ) {
        $this->contentService = $contentService;
        $this->stateService   = $stateService;
    }

    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getTitle()
    {
        return __('SEO Content');
    }

    /**
     * @return array|\Mirasvit\SeoToolbar\Api\Data\DataProviderItemInterface
     */
    public function getItems()
    {
        return [
            $this->getStateItem(),
            $this->getContentItem(),
            $this->getContentSourceItem(),
        ];
    }

    /**
     * @return array
     */
    private function getStateItem()
    {
        $state = [
            __('Is Category Page — %1', $this->stateService->isCategoryPage() ? 'Yes' : 'No'),
            __('Is Landing Page — %1', $this->stateService->isLandingPage() ? 'Yes' : 'No'),
            __('Is Navigation Page — %1', $this->stateService->isNavigationPage() ? 'Yes' : 'No'),
            __('Is Product Page — %1', $this->stateService->isProductPage() ? 'Yes' : 'No'),
            __('Is CMS page — %1', $this->stateService->isCmsPage() ? 'Yes' : 'No'),
            __('Is Blog Page — %1', $this->stateService->isBlogPage() ? 'Yes' : 'No'),
            __('Is Brand Page — %1', $this->stateService->isBlogPage() ? 'Yes' : 'No'),
        ];

        return [
            'title'       => (string)__('State'),
            'description' => implode(PHP_EOL, $state),
        ];
    }

    /**
     * @return array
     */
    private function getContentItem()
    {
        $content = $this->contentService->getCurrentContent();

        $templateId = $content->getData(ContentInterface::APPLIED_TEMPLATE_ID);
        $rewriteId  = $content->getData(ContentInterface::APPLIED_REWRITE_ID);

        $state = [
            __('Applied Template — %1', $templateId ? $templateId : 'None'),
            __('Applied Rewrite — %1', $rewriteId ? $rewriteId : 'None'),
        ];

        return [
            'title'       => (string)__('Content'),
            'description' => implode(PHP_EOL, $state)
        ];
    }

    /**
     * @return array
     */
    private function getContentSourceItem()
    {
        $content = $this->contentService->getCurrentContent();

        $properties = [
            ContentInterface::TITLE,
            ContentInterface::META_TITLE,
            ContentInterface::META_KEYWORDS,
            ContentInterface::META_DESCRIPTION,
            ContentInterface::DESCRIPTION,
            ContentInterface::SHORT_DESCRIPTION,
            ContentInterface::FULL_DESCRIPTION,
            ContentInterface::CATEGORY_DESCRIPTION,
            ContentInterface::BRAND_DESCRIPTION,
        ];

        $state = [];

        foreach ($properties as $property) {
            $state[] = $property . ' — ' . $content->getData($property . '_TOOLBAR');
        }

        return [
            'title'       => (string)__('Content Sources'),
            'description' => implode(PHP_EOL, $state)
        ];
    }
}
