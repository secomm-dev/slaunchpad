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

namespace Mirasvit\Seo\Service\Alternate;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Registry;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite as UrlRewrite;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;
use Mirasvit\Seo\Model\Config;

class ProductStrategy implements StrategyInterface
{
    private $url;

    private $config;

    private $registry;

    private $urlFinder;

    private $productRepository;

    private $frontNameResolver;

    public function __construct(
        UrlInterface               $url,
        Config                     $config,
        Registry                   $registry,
        UrlFinderInterface         $urlFinder,
        ProductRepositoryInterface $productRepository,
        FrontNameResolver          $frontNameResolver
    ) {
        $this->url               = $url;
        $this->config            = $config;
        $this->registry          = $registry;
        $this->urlFinder         = $urlFinder;
        $this->productRepository = $productRepository;
        $this->frontNameResolver = $frontNameResolver;
    }

    public function getStoreUrls(): array
    {
        $storeUrls = $this->url->getStoresCurrentUrl();

        return $this->getAlternateUrl($storeUrls);
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        if ($entityId) {
            $productId = $entityId;
            $stores    = $this->url->getStoresByStoreId($storeId);
        } else {
            $productId = $this->registry->registry('current_product')->getId();
            $stores    = $this->url->getStores();
        }

        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_ID => $productId,
            UrlRewrite::ENTITY_TYPE => 'product',
            UrlRewrite::REDIRECT_TYPE => 0
        ]);

        foreach ($stores as $storeId => $store) {
            /** @var Product $product */
            $product = $this->productRepository->getById($productId, false, $storeId);

            if ($product->getData('visibility') == Visibility::VISIBILITY_NOT_VISIBLE || !in_array($storeId, $product->getStoreIds())) {
                unset($storeUrls[$storeId]);
                continue;
            }

            $rewriteObject  = null;
            $rewriteObjects = [];

            foreach ($rewrites as $rewrite) {
                if ($rewrite->getStoreId() == $storeId) {
                    $requestPath         = $rewrite->getRequestPath();
                    $requestPathExploded = explode('/', $requestPath);
                    $categoryCount       = count($requestPathExploded);

                    $rewriteObjects[$categoryCount] = $rewrite;
                }
            }

            if ($rewriteObjects) {
                if ($this->config->isAddLongestCanonicalProductUrl((int)$storeId)
                    && $this->config->isProductLongUrlEnabled((int)$storeId)
                ) {
                    $rewriteObject = $rewriteObjects[max(array_keys($rewriteObjects))];
                } else {
                    $rewriteObject = $rewriteObjects[min(array_keys($rewriteObjects))];
                }
            }

            if ($rewriteObject && ($requestPath = $rewriteObject->getRequestPath())) {
                $storeUrls[$storeId] = $store->getBaseUrl() . $requestPath . $this->url->getUrlAddition($store);
            } elseif (!$rewriteObject) {
                $url = $product->getUrlInStore();

                // some products, such as those without a URL key or any rewrites created, may include a URL with an admin path
                if (strpos($url, $this->frontNameResolver->getFrontName()) !== false) {
                    $url = $store->getBaseUrl() . 'catalog/product/view/id/' . $product->getId();
                }

                $storeUrls[$storeId] = $url;
            }
        }

        if (count($storeUrls) === 1) {
            $storeUrls = []; // page doesn't have variations
        }

        return $this->url->processStoreParamForDuplicates($storeUrls);
    }
}
