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

namespace Mirasvit\SeoMarkup\Block\Rs;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\Data\Collection;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoMarkup\Model\Config;
use Mirasvit\SeoMarkup\Model\Config\AeoConfig;
use Mirasvit\SeoMarkup\Model\Config\BreadcrumbListConfig;

class BreadcrumbList extends Template
{
    private $store;

    private $breadcrumbListConfig;

    private $aeoConfig;

    private $catalogHelper;

    private $stateService;

    private $registry;

    private $serializer;

    public function __construct(
        BreadcrumbListConfig  $breadcrumbListConfig,
        AeoConfig             $aeoConfig,
        CatalogHelper         $catalogHelper,
        StateServiceInterface $stateService,
        Registry              $registry,
        Context               $context,
        Json                  $serializer
    ) {
        $this->breadcrumbListConfig = $breadcrumbListConfig;
        $this->aeoConfig            = $aeoConfig;
        $this->catalogHelper        = $catalogHelper;
        $this->stateService         = $stateService;
        $this->store                = $context->getStoreManager()->getStore();
        $this->registry             = $registry;
        $this->serializer           = $serializer;

        parent::__construct($context);
    }

    protected function _toHtml(): string
    {
        if (!$this->breadcrumbListConfig->isRsEnabled()) {
            return '';
        }

        $data = $this->getJsonData();

        if (!$data) {
            return '';
        }

        return '<script type="application/ld+json">' . $this->serializer->serialize($data) . '</script>';
    }

    public function getJsonData(): ?array
    {
        $crumbs = $this->registry->registry(BreadcrumbListConfig::REGISTER_KEY);

        if (!$crumbs && $this->stateService->isProductPage()) {
            $path = $this->getProductBreadcrumbPath($this->stateService->getProduct());

            $crumbs = [];
            foreach ($path as $item) {
                $url = $item['link'] ?? $this->_urlBuilder->getCurrentUrl();

                $crumbs[$url] = (string)$item['label'];
            }
        }

        if (!$crumbs || count($crumbs) === 0) {
            return null;
        }

        $data = [
            '@context'        => Config::HTTP_SCHEMA_ORG,
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [],
        ];

        if ($this->aeoConfig->isAeoEnabled()) {
            $data = [
                '@context'        => Config::HTTP_SCHEMA_ORG,
                '@type'           => 'BreadcrumbList',
                '@id'             => $this->_urlBuilder->getCurrentUrl() . AeoConfig::ID_BREADCRUMB,
                'itemListElement' => [],
            ];
        }

        $i = 1;
        foreach ($crumbs as $url => $label) {
            $data['itemListElement'][] = [
                '@type'    => "ListItem",
                'position' => $i,
                'item'     => [
                    '@id'  => $url,
                    'name' => strip_tags(trim($label)),
                ],
            ];

            $i++;
        }

        return $data;
    }

    private function getProductBreadcrumbPath(ProductInterface $product): array
    {
        $path = $this->catalogHelper->getBreadcrumbPath();

        if (count($path) > 1) {
            return $path;
        }

        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $product->getCategoryCollection();

        $collection
            ->addAttributeToSelect('is_active')
            ->addAttributeToSelect('name')
            ->setOrder('level', Collection::SORT_ORDER_DESC);

        $pool           = [];
        $targetCategory = null;

        $rootCategoryId = $this->store->getRootCategoryId();

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($collection as $category) {
            $pool[$category->getId()] = $category;

            if (!$category->getIsActive() || !in_array($rootCategoryId, explode('/', $category->getPath() ?? ''))) {
                continue;
            }

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

        $path = [];

        if ($targetCategory) {
            $pathInStore = $targetCategory->getPathInStore();
            $pathIds     = array_reverse(explode(',', $pathInStore));

            foreach ($pathIds as $categoryId) {
                if (isset($pool[$categoryId]) && $pool[$categoryId]->getName()) {
                    $category = $pool[$categoryId];

                    $path[] = [
                        'label' => $category->getName(),
                        'link'  => $category->getUrl(),
                    ];
                }
            }
        }

        $path[] = ['label' => $product->getName()];

        return $path;
    }
}
