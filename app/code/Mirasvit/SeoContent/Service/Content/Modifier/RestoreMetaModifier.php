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


declare(strict_types=1);

namespace Mirasvit\SeoContent\Service\Content\Modifier;

use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Core\Service\CompatibilityService;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\Seo\Service\TemplateEngine\Data\BrandData;
use Mirasvit\SeoContent\Api\Data\ContentInterface;
use Mirasvit\SeoContent\Model\Config;

/**
 * Purpose: Rewrite SEO meta, if product/category/CMS/blog/brand already have own meta
 */
class RestoreMetaModifier implements ModifierInterface
{
    private $stateService;

    private $storeManager;

    private $config;

    private $brandDataService;

    public function __construct(
        StateServiceInterface $stateService,
        StoreManagerInterface $storeManager,
        Config                $config,
        BrandData             $brandDataService
    ) {
        $this->stateService     = $stateService;
        $this->storeManager     = $storeManager;
        $this->config           = $config;
        $this->brandDataService = $brandDataService;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function modify(ContentInterface $content, ?string $forceApplyTo = null): ContentInterface
    {
        if (
            ($this->stateService->isProductPage() || $forceApplyTo == 'product')
            && $this->config->useProductMetaTags()
        ) {
            $product = $this->stateService->getProduct();

            $this->setMeta($content, ContentInterface::META_TITLE, $product->getData('meta_title'));
            $this->setMeta($content, ContentInterface::META_KEYWORDS, $product->getData('meta_keyword'));
            $this->setMeta($content, ContentInterface::META_DESCRIPTION, $product->getData('meta_description'));
        } elseif (
            ($this->stateService->isCategoryPage() || $forceApplyTo == 'category')
            && $this->config->useCategoryMetaTags()
        ) {
            $category = $this->stateService->getCategory();

            $this->setMeta($content, ContentInterface::TITLE, $category->getData('title_h1'));
            $this->setMeta($content, ContentInterface::META_TITLE, $category->getData('meta_title'));
            $this->setMeta($content, ContentInterface::META_KEYWORDS, $category->getData('meta_keyword'));
            $this->setMeta($content, ContentInterface::META_DESCRIPTION, $category->getData('meta_description'));

            // Magezon_PageBuilder fix
            $catDescription = '';
            foreach ($category->getData() as $k => $d) {
                if ($k == 'description') {
                    $catDescription = $d;
                    break;
                }
            }

            $this->setMeta($content, ContentInterface::CATEGORY_DESCRIPTION, $catDescription);
        } elseif (
            ($this->stateService->isCmsPage() || $forceApplyTo == 'cms')
            && $this->config->useCmsMetaTags()
        ) {
            $cmsPage = $this->stateService->getCmsPage() ?: CompatibilityService::getObjectManager()->get('\Magento\Cms\Api\Data\PageInterface');

            $this->setMeta($content, ContentInterface::TITLE, $cmsPage->getData('content_heading'));
            $this->setMeta($content, ContentInterface::META_TITLE, $cmsPage->getData('meta_title'));
            $this->setMeta($content, ContentInterface::META_KEYWORDS, $cmsPage->getData('meta_keywords'));
            $this->setMeta($content, ContentInterface::META_DESCRIPTION, $cmsPage->getData('meta_description'));
        } elseif (
            ($this->stateService->isBlogPage() || $forceApplyTo == 'blog')
            && $this->config->useBlogMetaTags()
            && $blogPage = $this->stateService->getBlogPage()
        ) {
            if ($blogPage instanceof \Mirasvit\BlogMx\Api\Data\TagInterface) {
                $title = (string)__('Tag: %1', $blogPage->getName());
            } else {
                $title = $content->getTitle() ?: $blogPage->getName();
            }

            $this->setMeta($content, ContentInterface::TITLE, $title);
            $this->setMeta($content, ContentInterface::META_TITLE, $blogPage->getData('meta_title'));
            $this->setMeta($content, ContentInterface::META_KEYWORDS, $blogPage->getData('meta_keywords'));
            $this->setMeta($content, ContentInterface::META_DESCRIPTION, $blogPage->getData('meta_description'));
        } elseif (
            ($this->stateService->isLandingPage() || $forceApplyTo == 'landing')
            && $this->config->useLandingMetaTags()
            && $landingPage = $this->stateService->getLandingPage()
        ) {
            $this->setMeta($content, ContentInterface::TITLE, $landingPage->getPageTitle());
            $this->setMeta($content, ContentInterface::META_TITLE, $landingPage->getMetaTitle());
            $this->setMeta($content, ContentInterface::META_DESCRIPTION, $landingPage->getMetaDescription());
        } elseif (
            ($this->stateService->isBrandPage() || $forceApplyTo == 'brand')
            && $this->config->useBrandMetaTags()
            && $brandPage = $this->stateService->getBrandPage()
        ) {
            $storeId = (int)$this->storeManager->getStore()->getId();

            $title            = $this->brandDataService->getBrandPageStoreValue($brandPage, \Mirasvit\Brand\Model\BrandPage::BRAND_TITLE, 'content', $storeId);
            $metaTitle        = $this->brandDataService->getBrandPageStoreValue($brandPage, \Mirasvit\Brand\Model\BrandPage::META_TITLE, 'meta_data', $storeId);
            $metaKeywords     = $this->brandDataService->getBrandPageStoreValue($brandPage, \Mirasvit\Brand\Model\BrandPage::KEYWORD, 'meta_data', $storeId);
            $metaDescription  = $this->brandDataService->getBrandPageStoreValue($brandPage, \Mirasvit\Brand\Model\BrandPage::META_DESCRIPTION, 'meta_data', $storeId);
            $brandDescription = $this->brandDataService->getBrandPageStoreValue($brandPage, \Mirasvit\Brand\Model\BrandPage::BRAND_DESCRIPTION, 'content', $storeId);

            $this->setMeta($content, ContentInterface::TITLE, $title);
            $this->setMeta($content, ContentInterface::META_TITLE, $metaTitle);
            $this->setMeta($content, ContentInterface::META_KEYWORDS, $metaKeywords);
            $this->setMeta($content, ContentInterface::META_DESCRIPTION, $metaDescription);
            $this->setMeta($content, ContentInterface::BRAND_DESCRIPTION, $brandDescription);
        }

        return $content;
    }

    private function setMeta(ContentInterface $content, string $property, ?string $value): void
    {
        if (!$value) {
            return;
        }

        $content->setData($property, $value);

        $content->setData(
            $property . '_TOOLBAR',
            "Default $property"
        );
    }
}
