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

namespace Mirasvit\Seo\Block\Catalog\Product;

use Exception;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;
use Mirasvit\Seo\Helper\Data as SeoHelper;
use Mirasvit\Seo\Model\Config;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;

class Breadcrumbs extends \Magento\Theme\Block\Html\Breadcrumbs
{
    private $catalogData;

    private $config;

    private $stateService;

    private $categoryCollectionFactory;

    private $seoHelper;

    private $productConfig;

    private $templateEngineService;

    private $moduleManager;

    private $objectManager;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context                        $context,
        Data                           $catalogData,
        Config                         $config,
        StateServiceInterface          $stateService,
        CategoryCollectionFactory      $categoryCollectionFactory,
        SeoHelper                      $seoHelper,
        ProductConfig                  $productConfig,
        TemplateEngineServiceInterface $templateEngineService,
        Manager                        $moduleManager,
        ObjectManagerInterface         $objectManager,
        array                          $data = [],
        ?Json                          $serializer = null
    ) {
        $this->catalogData               = $catalogData;
        $this->config                    = $config;
        $this->stateService              = $stateService;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->seoHelper                 = $seoHelper;
        $this->productConfig             = $productConfig;
        $this->templateEngineService     = $templateEngineService;
        $this->moduleManager             = $moduleManager;
        $this->objectManager             = $objectManager;

        parent::__construct($context, $data, $serializer);
    }

    public function addBreadcrumbs(): void
    {
        $breadcrumbs = [];

        $breadcrumbs['home'] = [
            'label' => __('Home'),
            'title' => __('Go to Home Page'),
            'link'  => $this->_storeManager->getStore()->getBaseUrl()
        ];

        $crumbs = [];

        if ($this->config->getProductBreadcrumbsAppearance() == Config::PRODUCT_BREADCRUMBS_APPEARANCE_DEFAULT) {
            $crumbs = $this->getDefaultCrumbs();
        } elseif ($this->config->getProductBreadcrumbsAppearance() == Config::PRODUCT_BREADCRUMBS_APPEARANCE_DEFAULT_FIRST) {
            $crumbs = $this->getDefaultFirstCrumbs();
        } elseif ($this->config->getProductBreadcrumbsAppearance() == Config::PRODUCT_BREADCRUMBS_APPEARANCE_CUSTOM) {
            $crumbs = $this->getCustomCrumbs();
        }

        $breadcrumbs = array_merge($breadcrumbs, $crumbs);

        $includeBrand = $this->config->getProductBreadcrumbsIncludeBrand();
        $brandFormat  = $this->config->getProductBreadcrumbsBrandFormat();
        $productBrand = $this->getProductBrand();

        $categoryCrumbInfo = null;
        foreach ($breadcrumbs as $crumbName => $crumbInfo) {
            if (strpos((string)$crumbName, 'category') !== false) {
                $categoryCrumbInfo = $crumbInfo;
            }

            if (($crumbName == 'product') && $includeBrand && $productBrand && $categoryCrumbInfo) {
                if ($brandFormat == Config::PRODUCT_BREADCRUMBS_BRAND_FORMAT_CATEGORY_BRAND) {
                    $label = $categoryCrumbInfo['label'] . ' ' . $productBrand['label'];
                } else {
                    $label = $productBrand['label'];
                }

                $link = $this->getBrandFilterUrl($categoryCrumbInfo['link'], $productBrand['code'], (string)$productBrand['value']);

                $brandCrumbInfo = [
                    'label' => $label,
                    'link'  => $link
                ];

                $this->addCrumb('brand', $brandCrumbInfo);
            }

            $this->addCrumb($crumbName, $crumbInfo);
        }
    }

    private function getDefaultCrumbs(): array
    {
        $path = $this->catalogData->getBreadcrumbPath();

        if ((count($path) == 1) && $this->seoHelper->getPreviousCategoryId()) {
            $crumbs = $this->getCrumbsByPreviousCategory();
        } else {
            $crumbs = $path;
        }

        return $crumbs;
    }

    private function getDefaultFirstCrumbs(): array
    {
        $path = $this->catalogData->getBreadcrumbPath();

        if (count($path) > 1) {
            $crumbs = $path;
        } elseif ($this->seoHelper->getPreviousCategoryId()) {
            $crumbs = $this->getCrumbsByPreviousCategory();
        } else {
            $crumbs = $this->getCustomCrumbs();
        }

        return $crumbs;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getCustomCrumbs(): array
    {
        $crumbs = [];

        $product = $this->stateService->getProduct();

        if (($this->config->getProductBreadcrumbsFormat() == Config::PRODUCT_BREADCRUMBS_FORMAT_SHORTEST)
            || ($this->config->getProductBreadcrumbsFormat() == Config::PRODUCT_BREADCRUMBS_FORMAT_LONGEST)
        ) {
            $categoryCollection = clone $product->getCategoryCollection();
            $categoryCollection->clear();
            $categoryCollection->addAttributeToSelect('name')
                ->addIsActiveFilter()
                ->addAttributeToFilter('path', ['like' => "1/" . $this->_storeManager->getStore()->getRootCategoryId() . "/%"])
                ->addAttributeToSort('level', \Magento\Framework\Data\Collection::SORT_ORDER_DESC);

            $pool           = [];
            $targetCategory = null;
            $rootCategoryId = $this->_storeManager->getStore()->getRootCategoryId();

            /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection */
            foreach ($categoryCollection as $category) {
                $pool[$category->getId()] = $category;

                // all parent categories must be active
                $child = $category;
                try {
                    while ($child->getLevel() > 1 && $parent = $child->getParentCategory()) {
                        $pool[$parent->getId()] = $parent;

                        if ($parent->getId() == $rootCategoryId) {
                            break;
                        }

                        if (!$parent->getIsActive()) {
                            $category = null;
                            break;
                        }

                        $child = $parent;
                    }
                } catch (Exception $e) {
                    // Not found exception is possible (corrupted data in DB)
                    $category = null;
                }

                if ($category) {
                    $targetCategory = $category;

                    break;
                }
            }

            if ($targetCategory) {
                $pathInStore = $targetCategory->getPathInStore();
                $pathIds     = array_reverse(explode(',', $pathInStore));

                if ($this->config->getProductBreadcrumbsFormat() == Config::PRODUCT_BREADCRUMBS_FORMAT_SHORTEST) {
                    $crumbs['category' . $targetCategory->getId()] = [
                        'label' => $targetCategory->getName(),
                        'link' => $targetCategory->getUrl()
                    ];
                } elseif (($this->config->getProductBreadcrumbsFormat() == Config::PRODUCT_BREADCRUMBS_FORMAT_LONGEST)) {
                    foreach ($pathIds as $categoryId) {
                        if (isset($pool[$categoryId]) && $pool[$categoryId]->getName()) {
                            $category = $pool[$categoryId];

                            $crumbs['category' . $category->getId()] = [
                                'label' => $category->getName(),
                                'link' => $category->getUrl()
                            ];
                        }
                    }
                }
            }
        }

        $crumbs['product'] = [
            'label' => $product->getName()
        ];

        return $crumbs;
    }

    private function getCrumbsByPreviousCategory(): array
    {
        $crumbs = [];

        if ($categoryId = $this->seoHelper->getPreviousCategoryId()) {
            $product = $this->stateService->getProduct();

            $categoryCollection = $this->categoryCollectionFactory->create();
            $categoryCollection->addIdFilter($categoryId);

            $breadcrumbCategories = $categoryCollection->getFirstItem()->getParentCategories();

            foreach ($breadcrumbCategories as $category) {
                $crumbs['category' . $category->getId()] = [
                    'label' => $category->getName(),
                    'link' => $category->getUrl()
                ];
            }

            $crumbs['product'] = [
                'label' => $product->getName()
            ];
        }

        return $crumbs;
    }

    private function getProductBrand(): ?array
    {
        $brand   = null;
        $name    = null;
        $product = $this->stateService->getProduct();

        if ($product) {
            $brandAttributes = $this->productConfig->getBrandAttributes();
            foreach ($brandAttributes as $attribute) {
                if (!$name) {
                    $name = $this->templateEngineService->render("[product_$attribute]", ['product' => $product]);
                }

                if ($name) {
                    $brand = [
                        'label' => $name,
                        'code'  => $attribute,
                        'value' => $product->getData($attribute)
                    ];
                    break;
                }
            }
        }

        return $brand;
    }

    private function getBrandFilterUrl(string $categoryLink, string $brandCode, string $brandValue): string
    {
        $moduleEnabled    = $this->moduleManager->isEnabled('Mirasvit_SeoFilter');
        $seoFilterEnabled = false;

        if ($moduleEnabled) {
            $seoFilterEnabled = $this->objectManager->get('\Mirasvit\SeoFilter\Model\ConfigProvider')->isEnabled();
        }

        if ($seoFilterEnabled) {
            $friendlyUrlService = $this->objectManager->get('\Mirasvit\SeoFilter\Service\FriendlyUrlService');

            $url = $friendlyUrlService->getUrl($brandCode, $brandValue, false, $categoryLink);
        } else {
            $url = $categoryLink . '?' . $brandCode . '=' . $brandValue;
        }


        return $url;
    }
}
