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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\SeoFilter\Service;

use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Mirasvit\SeoFilter\Api\Data\RewriteInterface;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Model\Context;
use Mirasvit\SeoFilter\Repository\RewriteRepository;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD)
 */
class MatchService
{
    const DECIMAL_FILTERS = 'decimalFilters';
    const STATIC_FILTERS  = 'staticFilters';

    private $splitting;

    private $rewriteRepository;

    private $urlRewrite;

    private $urlService;

    private $context;

    private $configProvider;

    private $objectManager;

    private $rewriteService;

    private $moduleManager;

    private $cacheService;

    private $storeManager;

    private $rewritesWithSeparator = [];

    private $urlFormatConfig;

    public function __construct(
        MatchService\Splitting      $splitting,
        RewriteRepository           $rewriteRepository,
        RewriteService              $rewriteService,
        UrlRewriteCollectionFactory $urlRewrite,
        UrlService                  $urlService,
        ConfigProvider              $configProvider,
        ObjectManagerInterface      $objectManager,
        Manager                     $moduleManager,
        Context                     $context,
        CacheService                $cacheService,
        StoreManagerInterface       $storeManager
    ) {
        $this->splitting         = $splitting;
        $this->rewriteRepository = $rewriteRepository;
        $this->rewriteService    = $rewriteService;
        $this->urlRewrite        = $urlRewrite;
        $this->urlService        = $urlService;
        $this->configProvider    = $configProvider;
        $this->objectManager     = $objectManager;
        $this->moduleManager     = $moduleManager;
        $this->context           = $context;
        $this->cacheService      = $cacheService;
        $this->storeManager      = $storeManager;
        $this->urlFormatConfig   = $configProvider->getUrlFormatConfig();
    }

    public function getParams(): ?array
    {
        if ($this->isNativeRewrite()) {
            return null;
        }


        $categoryId       = 0;
        $isBrandPage      = false;
        $isAllProductPage = false;
        $isLandingPage    = false;

        //        $currentUrl = $this->context->getUrlBuilder()->getCurrentUrl();
        //        $urlPath    = parse_url($currentUrl, PHP_URL_PATH);

        $urlPath = $this->context->getRequest()->getOriginalPathInfo();

        $baseUrlPathAll = 'all';

        if ($this->moduleManager->isEnabled('Mirasvit_AllProducts')) {
            $allProductConfig = $this->objectManager->get('\Mirasvit\AllProducts\Config\Config');

            $baseUrlPathAll = $allProductConfig->isEnabled() ? $allProductConfig->getUrlKey() : $baseUrlPathAll;
        }

        $brandRouteParams   = $this->getBaseBrandUrlPath();
        $baseUrlPathBrand   = $brandRouteParams['brandPath'];

        $landingRouteParams = $this->getLandingPageUrlPath();
        $landingUrl         = $landingRouteParams['page_url'];

        $baseUrlPathCategory = '';

        if (preg_match('~^/' . $baseUrlPathAll . '/\S+~', $urlPath)) {
            $isAllProductPage = true;
        } elseif ($landingUrl && strpos($urlPath, $landingUrl) !== false) {
            $isLandingPage = true;
        } elseif (preg_match('~^/' . $baseUrlPathBrand . '/\S+~', $urlPath)) {
            $isBrandPage = true;
        } else {
            $categoryId = $this->getCategoryId();
        }
        if (!$categoryId && !$isBrandPage && !$isAllProductPage && !$isLandingPage) {
            return null;
        }

        if ($categoryId) {
            $baseUrlPathCategory = $this->getCategoryBaseUrlPath($categoryId);

            $categoryRouteParams = [
                'category_id' => $categoryId,
                'baseUrlPath' => $baseUrlPathCategory
            ];
        }

        if ($isBrandPage) {
            $baseUrlPath = $baseUrlPathBrand;
        } elseif ($isLandingPage) {
            $baseUrlPath = $landingUrl;
        } elseif ($isAllProductPage) {
            $baseUrlPath = $baseUrlPathAll;
        } else {
            $baseUrlPath = $baseUrlPathCategory;
        }

        $pageType = '';

        if ($isBrandPage) {
            $pageType = MatchService\Splitting::BRAND_PAGE;
        } elseif ($isLandingPage) {
            $pageType = MatchService\Splitting::LANDING_PAGE;
        }

        $filterData = $baseUrlPath ? $this->splitting->getFiltersString($baseUrlPath, $pageType) : [];

        $staticFilters  = [];
        $decimalFilters = [];

        $decimalFilters = $this->handleDecimalFilters($filterData, $decimalFilters);

        $staticFilters = $this->handleStockFilters($filterData, $staticFilters);
        $staticFilters = $this->handleRatingFilters($filterData, $staticFilters);
        $staticFilters = $this->handleSaleFilters($filterData, $staticFilters);
        $staticFilters = $this->handleNewFilters($filterData, $staticFilters);
        $staticFilters = $this->handleAttributeFilters($filterData, $staticFilters);

        $params = [];

        foreach ($decimalFilters as $attr => $values) {
            $params[$attr] = implode(ConfigProvider::SEPARATOR_FILTER_VALUES, $values);
        }

        foreach ($staticFilters as $attr => $values) {
            $params[$attr] = implode(ConfigProvider::SEPARATOR_FILTER_VALUES, $values);
        }

        $match = count($filterData) == 0;

        $result = [
            'all_products_route'       => $isAllProductPage ? $baseUrlPathAll : null,
            'landing_page_route_data'  => $isLandingPage ? $landingRouteParams : [],
            'brand_page_route_data'    => $isBrandPage ? $brandRouteParams : [],
            'category_page_route_data' => $categoryId ? $categoryRouteParams : [],
            'params'                   => $params,
            'match'                    => $match,
        ];

        $result = $this->checkIfRouteExists($result);

        if (!empty($result['landing_page_route_data'])) {
            $result['params'] = $this->addLandingSearchParam($result);
        }

        return $result;
    }

    private function addLandingSearchParam(array $result): array
    {
        $searchTerm = $result['landing_page_route_data']['search_term'];

        if ($searchTerm) {
            $result['params']['landing_search'] = $searchTerm;
        }

        return $result['params'];
    }

    private function getLandingPageUrlPath(): array
    {
        $urlPath = parse_url($this->context->getUrlBuilder()->getCurrentUrl(), PHP_URL_PATH);

        if (
            class_exists('Mirasvit\LandingPage\Model\Url\UrlParser')
            && $this->moduleManager->isEnabled('Mirasvit_LandingPage')
            && is_string($urlPath)
        ) {

            $pageUrlParser = $this->objectManager->get('Mirasvit\LandingPage\Model\Url\UrlParser');
            $landing = $pageUrlParser->findPage($urlPath);

            if ($landing) {
                return [
                    'page_url'    => $landing->getUrlKey(),
                    'page_id'     => intval($landing->getData('page_id')),
                    'search_term' => $landing->getSearchTerm()
                ];
            }
        }

        return ['page_url' => null, 'page_id' => null, 'search_term' => null];
    }

    private function getBaseBrandUrlPath(): array
    {
        $brandPath   = 'brand';
        $brandId     = null;
        $brandUrlKey = null;

        $urlPath = parse_url($this->context->getUrlBuilder()->getCurrentUrl(), PHP_URL_PATH);

        if (
            !is_string($urlPath)
            || !class_exists('Mirasvit\Brand\Model\Config\GeneralConfig')
            || !$this->moduleManager->isEnabled('Mirasvit_Brand')
        ) {
            return [
                'brandPath'        => $brandPath,
                'brandId'          => $brandId,
                'brandUrlKey'      => $brandUrlKey,
                'isShortFormatUrl' => true
            ];
        }

        /** @var \Mirasvit\Brand\Model\Config\GeneralConfig|object $brandConfig */
        $brandConfig = $this->objectManager->get('Mirasvit\Brand\Model\Config\GeneralConfig');

        $brandPath = $brandConfig->getAllBrandUrl();
        $isShortFormatBrandUrl = $brandConfig->getFormatBrandUrl() == 1 ? true : false;

        /** @var \Mirasvit\Brand\Repository\BrandRepository|object $brandRepository */
        $brandRepository = $this->objectManager->get('Mirasvit\Brand\Repository\BrandRepository');

        $store = $this->storeManager->getStore();
        // exclude store_code from url
        if ($store->isUseStoreInUrl()) {
            $storeCode = preg_quote('/' . $store->getCode(), '/');
            $pattern   = "@^($storeCode)@";
            $urlPath   = (string)preg_replace($pattern, '', $urlPath);
        }

        foreach ($brandRepository->getList() as $brand) {
            if (preg_match('/\/' . $brand->getUrlKey() . '\/\S*/', rtrim($urlPath, '/') . '/')) {
                $brandId     = $brand->getId();
                $brandUrlKey = $brand->getUrlKey();
                if ($brandConfig->getFormatBrandUrl() == 1) {
                    $brandPath = $brand->getUrlKey();
                    break;
                } else {
                    $brandPath = $brandConfig->getAllBrandUrl() . '/' . $brand->getUrlKey();
                    break;
                }
            }
        }

        return [
            'brandPath'        => $brandPath,
            'brandId'          => $brandId,
            'brandUrlKey'      => $brandUrlKey,
            'isShortFormatUrl' => $isShortFormatBrandUrl
        ];
    }

    private function getCategoryId(): ?int
    {
        $requestPath  = trim($this->context->getRequest()->getOriginalPathInfo(), '/');
        $storeId      = $this->context->getStoreId();
        $originalPath = $requestPath . '-' . $storeId;

        $cached = $this->cacheService->getCache('getCategoryId', [$originalPath]);
        if ($cached !== null) {
            $id = (int)array_values($cached)[0];
            return $id > 0 ? $id : null;
        }

        if ($categoryId = $this->getCategoryIdFromIndex($requestPath, $storeId)) {
            return $categoryId;
        }

        if ($categoryId = $this->getCategoryIdByPath($requestPath)) {
            $basePath = $this->removeCategorySuffix($requestPath);
            $this->cacheService->setCache('getCategoryId', [$originalPath], [$categoryId], CacheService::REWRITE_CACHE_TTL);
            $this->setCategoryIdByBasePath($basePath, $storeId, $categoryId);

            return (int)$categoryId;
        }

        $categoryRewriteCollection = $this->urlRewrite->create()
            ->addFieldToFilter('entity_type', 'category')
            ->addFieldToFilter('store_id', $storeId)
            ->setOrder('request_path', 'DESC');

        $categorySuffix = $this->urlService->getCategoryUrlSuffix();

        $categoryBasePath = '';

        foreach ($categoryRewriteCollection as $categoryRewrite) {
            $path = $this->removeCategorySuffix($categoryRewrite->getRequestPath());
            if (substr($path, -1) == '/') {
                $path = substr($path, 0, -1);
            }
            if (strpos($requestPath, $path . '/') === 0 && strlen($path) > strlen($categoryBasePath)) {
                $categoryBasePath = $path;
                break;
            }
        }

        if (empty($categoryBasePath) && strpos($requestPath, 'catalog/category/view') !== false) {
            if (preg_match('/id\/(\d*)/', $requestPath, $match)) {
                return (int)$match[1];
            }
        }

        if (empty($categoryBasePath)) {
            $this->cacheService->setCache('getCategoryId', [$originalPath], [0], CacheService::REWRITE_CACHE_TTL);
            return null;
        }

        $filtersData = $this->splitting->getFiltersString($categoryBasePath);
        $rewrites    = $this->rewriteRepository->getCollection();
        $requestPath = $this->removeCategorySuffix($requestPath);
        $prefix      = $this->configProvider->getPrefix();

        if ($prefix) {
            if (strripos($requestPath, '/' . $prefix . '/') !== false) {
                $requestPath = str_replace('/' . $prefix . '/', '/', $requestPath);
            } else {
                return null;
            }
        }

        if (isset($filtersData['*'])) {
            $filtersData = $filtersData['*'];
        }

        $filterOptions = [];
        $staticFilters = [];

        if ($this->configProvider->getUrlFormat() == ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
            $fData = $filtersData;

            $staticFilters = $this->handleStockFilters($fData, $staticFilters);
            $staticFilters = $this->handleRatingFilters($fData, $staticFilters);
            $staticFilters = $this->handleSaleFilters($fData, $staticFilters);
            $staticFilters = $this->handleNewFilters($fData, $staticFilters);
        }

        foreach ($filtersData as $attribute => $filter) {
            if ($this->configProvider->getUrlFormat() == ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
                $requestData = explode('/', $requestPath);

                $rewrites = $this->rewriteRepository->getCollection()
                    ->addFieldToFilter(\Mirasvit\SeoFilter\Api\Data\RewriteInterface::STORE_ID, $storeId)
                    ->addFieldToFilter(\Mirasvit\SeoFilter\Api\Data\RewriteInterface::ATTRIBUTE_CODE, $attribute)
                    ->addFieldToFilter(\Mirasvit\SeoFilter\Api\Data\RewriteInterface::OPTION, ['null' => true]);
                foreach ($rewrites as $rewrite) {
                    if (ConfigProvider::URL_FORMAT_LONG_SLASH != $this->urlFormatConfig->getFormat()) {
                        foreach ($requestData as $key => $data) {
                            if (strpos($data, $this->urlFormatConfig->getAttributeSeparator()) !== false) {
                                $preparedData = explode($this->urlFormatConfig->getAttributeSeparator(), $data);
                                if (array_search($rewrite->getRewrite(), $preparedData) !== false) {
                                    unset($requestData[$key]);
                                    break;
                                }
                            }
                        }
                    } else {
                        $attributeKey = array_search($rewrite->getRewrite(), $requestData);
                        unset($requestData[$attributeKey + 1]);
                        unset($requestData[$attributeKey]);
                    }
                }

                $requestPath = implode('/', $requestData);
            } else {
                $filterOptions[] = $filter;
            }
        }

        if (count($filterOptions)) {
            $filterString = implode($this->urlFormatConfig->getAttributeSeparator(), $filterOptions);

            if (strrpos($requestPath, $filterString) !== false) {
                // substr_replace because category path can include option alias
                $requestPath = substr_replace(
                    $requestPath,
                    '',
                    strrpos($requestPath, $filterString),
                    strlen($filterString)
                );
            }
        }

        $requestPath = trim($requestPath, '/-');
        $requestPath .= $categorySuffix;

        // category rewrites can be with / at the end of the path
        $catId = $this->getCategoryIdByPath($requestPath) ?: $this->getCategoryIdByPath($requestPath . '/');

        if ($catId) {
            $this->setCategoryIdByBasePath($categoryBasePath, $storeId, $catId);
        } else {
            $this->cacheService->setCache('getCategoryId', [$originalPath], [0], CacheService::REWRITE_CACHE_TTL);
        }

        return $catId;
    }

    private function getCategoryIdFromIndex(string $requestPath, int $storeId): ?int
    {
        $index = $this->cacheService->getCache('getCategoryIdIndex', [(string)$storeId]);
        if (empty($index)) {
            return null;
        }

        $cleanPath = $this->removeCategorySuffix($requestPath);

        $base = null;
        $id   = null;
        foreach ($index as $basePath) {
            if ($cleanPath === $basePath || strpos($cleanPath, $basePath . '/') === 0) {
                $cached = $this->cacheService->getCache('getCategoryIdByBase', [$basePath, (string)$storeId]);
                if ($cached) {
                    $base = $basePath;
                    $id   = (int)array_values($cached)[0];
                    break;
                }
            }
        }

        if ($base === null || $id === null) {
            return null;
        }

        return $this->resolveDeepestCategory($cleanPath, $base, $id, $storeId);
    }
    
    private function resolveDeepestCategory(string $cleanPath, string $base, int $id, int $storeId): int
    {
        $suffix = $this->urlService->getCategoryUrlSuffix();

        while (strlen($cleanPath) > strlen($base)) {
            $nextSegment = explode('/', substr($cleanPath, strlen($base) + 1))[0];
            if ($nextSegment === '') {
                break;
            }
            $candidate   = $base . '/' . $nextSegment;
            $candidateId = $this->getCategoryIdByPath($candidate . $suffix)
                ?: $this->getCategoryIdByPath($candidate . $suffix . '/');

            if (!$candidateId) {
                break;
            }

            $base = $candidate;
            $id   = $candidateId;
            $this->setCategoryIdByBasePath($base, $storeId, $id);
        }

        return $id;
    }

    private function setCategoryIdByBasePath(string $basePath, int $storeId, int $categoryId): void
    {
        $this->cacheService->setCache('getCategoryIdByBase', [$basePath, (string)$storeId], [$categoryId], CacheService::REWRITE_CACHE_TTL);

        $index = $this->cacheService->getCache('getCategoryIdIndex', [(string)$storeId]) ?? [];
        if (in_array($basePath, $index, true)) {
            return;
        }

        $index = $this->cacheService->getCache('getCategoryIdIndex', [(string)$storeId]) ?? [];
        if (!in_array($basePath, $index, true)) {
            $index[] = $basePath;
        }
        usort($index, static function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $this->cacheService->setCache('getCategoryIdIndex', [(string)$storeId], [$index], CacheService::REWRITE_CACHE_TTL);
    }

    private function removeCategorySuffix(string $path): string
    {
        $categorySuffix = $this->urlService->getCategoryUrlSuffix();

        if (!$categorySuffix || substr_compare($path, $categorySuffix, -strlen($categorySuffix)) !== 0) {
            return $path;
        }

        $suffixPosition = strrpos($path, $categorySuffix);

        return $suffixPosition !== false
            ? substr($path, 0, $suffixPosition)
            : $path;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // private function collectCorrectFilterOptions(array $filter, string $attribute = null): array
    // {
    //     $found = [];

    //     $rewrites = $this->rewriteRepository->getCollection();

    //     $isRange = true;

    //     if ($attribute && !$this->context->isDecimalAttribute($attribute)) {
    //         $isRange = false;
    //     }

    //     foreach ($filter as $value) {
    //         if ($this->isStaticFilterRewrite($value) || ($attribute && $isRange) || is_numeric($value)) {
    //             $found[] = $value;
    //         } else {
    //             foreach ($rewrites as $rewrite) {
    //                 if ($value === $rewrite->getRewrite()) {
    //                     $found[] = $value;
    //                 }
    //             }
    //         }
    //     }

    //     sort($found);

    //     return $found;
    // }

    // private function ensureAttributeRewrite(string $alias): ?string
    // {
    //     $staticFilterLables = [
    //         ConfigProvider::FILTER_RATING,
    //         ConfigProvider::FILTER_NEW,
    //         ConfigProvider::FILTER_SALE,
    //         ConfigProvider::FILTER_STOCK
    //     ];

    //     return $this->rewriteService->getAttributeRewriteByAlias($alias, $this->context->getStoreId()) || in_array($alias, $staticFilterLables)
    //         ? $alias
    //         : null;
    // }

    private function getCategoryIdByPath(string $requestPath): ?int
    {
        $categoryRewrite = $this->urlRewrite
            ->create()
            ->addFieldToFilter('store_id', $this->context->getStoreId())
            ->addFieldToFilter('entity_type', 'category')
            ->addFieldToFilter('request_path', $requestPath)
            ->getFirstItem();

        return $categoryRewrite && $categoryRewrite->getEntityId() ? (int)$categoryRewrite->getEntityId() : null;
    }

    // private function isStaticFilterRewrite(string $value): bool
    // {
    //     $staticFilters = [
    //         ConfigProvider::FILTER_SALE,
    //         ConfigProvider::FILTER_NEW,
    //         ConfigProvider::LABEL_RATING_1,
    //         ConfigProvider::LABEL_RATING_2,
    //         ConfigProvider::LABEL_RATING_3,
    //         ConfigProvider::LABEL_RATING_4,
    //         ConfigProvider::LABEL_RATING_5,
    //         ConfigProvider::LABEL_STOCK_IN,
    //         ConfigProvider::LABEL_STOCK_OUT,
    //     ];

    //     return in_array($value, $staticFilters);
    // }

    private function getCategoryBaseUrlPath(int $categoryId): string
    {
        /** @var \Magento\UrlRewrite\Model\UrlRewrite $item */
        $item = $this->urlRewrite->create()
            ->addFieldToFilter('entity_type', 'category')
            ->addFieldToFilter('redirect_type', 0)
            ->addFieldToFilter('store_id', $this->context->getStoreId())
            ->addFieldToFilter('entity_id', $categoryId)
            ->getFirstItem();

        $url = (string)$item->getData('request_path');

        if (!$url) {
            $urlPath = trim($this->context->getRequest()->getOriginalPathInfo(), '/');

            if (
                strpos($urlPath, 'catalog/category/view') !== false
                && strpos($urlPath, (string)$categoryId) !== false
            ) {
                $categoryId = (string)$categoryId;

                $url = substr($urlPath, 0, strpos($urlPath, $categoryId) + strlen($categoryId));
            }
        }


        return $this->removeCategorySuffix($url);
    }

    private function isNativeRewrite(): bool
    {
        $requestString = trim($this->context->getRequest()->getPathInfo(), '/');

        $requestPathRewrite = $this->urlRewrite->create()
            ->addFieldToFilter('entity_type', 'category')
            ->addFieldToFilter('redirect_type', 0)
            ->addFieldToFilter('store_id', $this->context->getStoreId())
            ->addFieldToFilter('request_path', $requestString);

        return $requestPathRewrite->getSize() > 0;
    }

    private function handleStockFilters(array &$filterData, array $staticFilters): array
    {
        $options = [
            1 => ConfigProvider::LABEL_STOCK_OUT,
            2 => ConfigProvider::LABEL_STOCK_IN,
        ];

        return $this->processBuiltInFilters(ConfigProvider::FILTER_STOCK, $options, $filterData, $staticFilters);
    }

    private function handleRatingFilters(array &$filterData, array $staticFilters): array
    {
        $options = [
            1 => ConfigProvider::LABEL_RATING_1,
            2 => ConfigProvider::LABEL_RATING_2,
            3 => ConfigProvider::LABEL_RATING_3,
            4 => ConfigProvider::LABEL_RATING_4,
            5 => ConfigProvider::LABEL_RATING_5,
        ];

        return $this->processBuiltInFilters(ConfigProvider::FILTER_RATING, $options, $filterData, $staticFilters);
    }

    private function handleSaleFilters(array &$filterData, array $staticFilters): array
    {
        $options = [
            0 => ConfigProvider::LABEL_SALE_NO,
            1 => ConfigProvider::LABEL_SALE_YES,
        ];

        return $this->processBuiltInFilters(ConfigProvider::FILTER_SALE, $options, $filterData, $staticFilters);
    }

    private function handleNewFilters(array &$filterData, array $staticFilters): array
    {
        $options = [
            0 => ConfigProvider::FILTER_NEW . '_no',
            1 => ConfigProvider::FILTER_NEW . '_yes',
        ];

        return $this->processBuiltInFilters(ConfigProvider::FILTER_NEW, $options, $filterData, $staticFilters);
    }

    private function handleAttributeFilters(array &$filterData, array $staticFilters): array
    {
        foreach ($filterData as $attrCode => $filterValues) {
            $rewriteCollection = $this->rewriteRepository->getCollection()
                ->addFieldToFilter(RewriteInterface::REWRITE, ['in' => $filterValues])
                ->addFieldToFilter(RewriteInterface::STORE_ID, $this->context->getStoreId());

            if ($attrCode != '*') {
                $rewriteCollection->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attrCode);
            }

            if ($rewriteCollection->getSize() == count($filterValues)) {
                /** @var RewriteInterface $rewrite */
                foreach ($rewriteCollection as $rewrite) {
                    $rewriteAttributeCode = $rewrite->getAttributeCode();
                    $optionId             = $rewrite->getOption();

                    $staticFilters[$rewriteAttributeCode][] = $optionId;
                    $this->checkIfRewriteHasSeparator($rewrite);
                }

                unset($filterData[$attrCode]);
            } else {
                $rewriteCollection = $this->rewriteRepository->getCollection()
                    ->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attrCode)
                    ->addFieldToFilter(RewriteInterface::STORE_ID, $this->context->getStoreId())
                    ->addFieldToFilter(RewriteInterface::OPTION, ['notnull' => true]);

                $rewrites = [];
                foreach ($rewriteCollection as $rewrite) {
                    $rewrites[$rewrite->getOption()] = $rewrite->getRewrite();
                    $this->checkIfRewriteHasSeparator($rewrite);
                }
                $filterString = implode('-', $filterValues);

                foreach ($rewrites as $optionId => $rew) {
                    $str = str_replace($rew, '', $filterString);
                    if ($filterString != $str) {
                        $filterString = $str;

                        $staticFilters[$attrCode][] = $optionId;
                    }
                }
                unset($filterData[$attrCode]);
            }
        }

        return $staticFilters;
    }

    private function handleDecimalFilters(array &$filterData, array $decimalFilters): array
    {
        foreach ($filterData as $attrCode => $filterValues) {
            if ($attrCode != '*') {
                if ($this->context->isDecimalAttribute($attrCode)) {
                    $option = implode(ConfigProvider::SEPARATOR_FILTERS, $filterValues);

                    $decimalFilters[$attrCode][] = $option;

                    unset($filterData[$attrCode]);
                }
            } else {
                foreach ($filterValues as $key => $filterValue) {
                    if (strpos($filterValue, ConfigProvider::SEPARATOR_DECIMAL) !== false) {
                        $exploded = explode(ConfigProvider::SEPARATOR_DECIMAL, $filterValue);
                        $attrCode = $exploded[0];
                        unset($exploded[0]);
                        $option                      = implode(ConfigProvider::SEPARATOR_FILTERS, $exploded);
                        $decimalFilters[$attrCode][] = $option;

                        unset($filterData['*'][$key]);
                    }
                }
            }
        }

        return $decimalFilters;
    }

    private function processBuiltInFilters(string $attrCode, array $options, array &$filterData, array $staticFilters): array
    {
        foreach ($options as $key => $label) {
            foreach ($filterData as $fKey => $value) {
                if (in_array($label, $value)) {
                    $staticFilters[$attrCode][] = $key;

                    $vKey = array_search($label, $filterData[$fKey]);
                    $rewrite = [
                        'alias' => $filterData[$fKey][$vKey],
                        'attrCode' => $attrCode,
                        'option' => $key,
                    ];
                    $this->checkIfRewriteHasSeparator($rewrite);
                    unset($filterData[$fKey][$vKey]);
                }
            }
        }

        return $staticFilters;
    }

    private function checkIfRouteExists(array $result): array
    {
        if (empty($result['params'])) {
            $result['match'] = false;
            return $result;
        }

        $requestPath  = trim($this->context->getRequest()->getOriginalPathInfo(), '/');
        $allParts     = explode('/', $requestPath);

        // define url parts count without filters
        if (!empty($result['category_page_route_data'])) {
            $baseParts = explode('/', trim((string)$result['category_page_route_data']['baseUrlPath'], '/'));
        } elseif (!empty($result['brand_page_route_data'])) {
            $baseParts = explode('/', $result['brand_page_route_data']['brandUrlKey'] ?? '');
        } elseif ($result['all_products_route']) {
            $baseParts = explode('/', $result['all_products_route']);
        } elseif (!empty($result['landing_page_route_data'])) {
            $baseParts = explode('/', trim($result['landing_page_route_data']['page_url'], '/'));
        } else {
            $baseParts = [];
        }

        $basePartsCount = count($baseParts);

        if (
            isset($result['brand_page_route_data'])
            && isset($result['brand_page_route_data']['isShortFormatUrl'])
            && !$result['brand_page_route_data']['isShortFormatUrl']
        ) {
            $basePartsCount += 1;
        }

        $prefix = $this->configProvider->getPrefix();

        if ($prefix) {
            $basePartsCount++;
        }

        if ($this->configProvider->getUrlFormat() === ConfigProvider::URL_FORMAT_OPTIONS) {
            $result['match'] = $this->urlFormatConfig->getFormat() === ConfigProvider::URL_FORMAT_SHORT_SLASH
                ? $this->validatePartsFormatShortSlash($basePartsCount, $result, $allParts, $baseParts, $prefix)
                : $this->validatePartsFormatShort($basePartsCount, $result, $allParts);
        } else {
            $result['match'] = $this->urlFormatConfig->getFormat() === ConfigProvider::URL_FORMAT_LONG_SLASH
                ? $this->validatePartsFormatLongSlash($basePartsCount, $result, $allParts)
                : $this->validatePartsFormatLongDashOrColon($basePartsCount, $result, $allParts);
        }

        return $result;
    }

    private function replaceAttributeCodeWithAlias(array $result): array
    {
        foreach ($result['params'] as $code => $value) {
            $rewrite = $this->rewriteService->getAttributeRewrite($code);
            if ($rewrite) {
                $alias = $rewrite->getRewrite();
                if ($alias !== $code) {
                    $result['params'][$alias] = $value;
                    unset($result['params'][$code]);
                }
            }
        }

        return $result;
    }

    private function validatePartsFormatLongSlash(int $basePartsCount, array $result, array $allParts): bool
    {
        $validFilters = true;

        // initial position of the first attribute in long-url
        $attributeIndex = $basePartsCount + 1;
        $result = $this->replaceAttributeCodeWithAlias($result);

        foreach ($allParts as $key => $part) {
            if ($key != $attributeIndex) {
                continue;
            }
            $validFiltersCount = 0;

            $filtersCountInUrl = isset($allParts[$attributeIndex]) ? count(explode('-', $allParts[$attributeIndex])) : 0;

            $attributeValue = isset($result['params'][$allParts[$key - 1]]) ? $result['params'][$allParts[$key - 1]] : null;

            $attributeRewrite = isset($allParts[$key - 1]) ? $allParts[$key - 1] : null;

            $storeId = intval($this->storeManager->getStore()->getId());
            $attributeRewriteModel = $this->rewriteService->getAttributeRewriteByAlias(strval($attributeRewrite), $storeId);
            $attributeCode = $attributeRewriteModel ? $attributeRewriteModel->getAttributeCode() : null;

            if ($filtersCountInUrl && $attributeValue) {

                $separator = $attributeCode && $this->context->isDecimalAttribute($attributeCode) ? '-' : ',';

                $options = explode($separator, $attributeValue);

                $validFiltersCount += count($options);

                if ($separator === ',' && !empty($this->rewritesWithSeparator)) {
                    $validFiltersCount += $this->handleRewritesWithSeparator($options, $attributeCode);
                }

                if ($filtersCountInUrl != $validFiltersCount) {
                    $validFilters = false;
                    break;
                }
            }

            $attributeIndex += 2;
        }

        return count($allParts) - $basePartsCount == count($result['params']) * 2 && $validFilters;
    }

    private function validatePartsFormatLongDashOrColon(int $basePartsCount, array $result, array $allParts): bool
    {
        $validFilters = true;

        $isLongColonFormat = ConfigProvider::URL_FORMAT_LONG_COLON === $this->urlFormatConfig->getFormat();

        $attributeIndex = $basePartsCount;
        foreach ($allParts as $key => $part) {
            if ($key != $attributeIndex) {
                continue;
            }
            $validFiltersCount = 0;
            $attributeValue    = null;
            $attributeCode     = null;

            if (isset($allParts[$attributeIndex])) {
                $filterData = $this->handleFilterLine($allParts[$attributeIndex], $result['params']);

                if (empty($filterData)) {
                    return false;
                }

                $filtersCountInUrl = count(explode($this->urlFormatConfig->getOptionSeparator(), $filterData['filterLine']));
                $attributeRewrite = $filterData['attributeAlias'];

                $storeId = intval($this->storeManager->getStore()->getId());
                $attributeRewriteModel = $this->rewriteService->getAttributeRewriteByAlias(strval($attributeRewrite), $storeId);
                $attributeCode = $attributeRewriteModel ? $attributeRewriteModel->getAttributeCode() : null;

                $attributeValue = $filterData['attributeValue'];
            } else {
                return false;
            }

            if ($filtersCountInUrl && $attributeValue) {

                $separator = $attributeCode && $this->context->isDecimalAttribute($attributeCode) && !$isLongColonFormat
                    ? '-'
                    : ',';

                $options = explode($separator, $attributeValue);
                $validFiltersCount += count($options);

                if ($separator === ',' && !empty($this->rewritesWithSeparator) && !$isLongColonFormat) {
                    $validFiltersCount += $this->handleRewritesWithSeparator($options, $attributeCode);
                }

                if ($filtersCountInUrl != $validFiltersCount) {
                    $validFilters = false;
                    break;
                }
            }

            $attributeIndex += 1;
        }

        return count($allParts) - $basePartsCount == count($result['params']) && $validFilters;
    }

    private function handleFilterLine(string $filtersPart, array $params): array
    {
        $attributeKeys = array_keys($params);
        $separator = $this->urlFormatConfig->getAttributeSeparator();
        foreach ($attributeKeys as $attributeCode) {
            $attributeRewrite = $this->rewriteService->getAttributeRewrite($attributeCode, $this->context->getStoreId());

            $attributeAlias = $attributeRewrite->getRewrite();

            $aliasWithSeparator = $attributeAlias . $separator;
            if (substr($filtersPart, 0, strlen($aliasWithSeparator)) === $aliasWithSeparator) {
                $filterLine = substr($filtersPart, strlen($aliasWithSeparator));
                return [
                    'filterLine' => $filterLine,
                    'attributeAlias' => $attributeAlias,
                    'attributeValue' => $params[$attributeCode]
                ];
            }
        }
        return [];
    }

    private function validatePartsFormatShort(int $basePartsCount, array $result, array $allParts): bool
    {
        $validFiltersCount = 0;

        $filtersCountInUrl = isset($allParts[$basePartsCount])
            ? count(explode($this->urlFormatConfig->getOptionSeparator(), $allParts[$basePartsCount]))
            : 0;
        foreach ($result['params'] as $attr => $item) {
            $options = explode(',', $item);
            $validFiltersCount += count($options);
            if (!empty($this->rewritesWithSeparator)) {
                $validFiltersCount += $this->handleRewritesWithSeparator($options, $attr);
            }
        }

        return $basePartsCount + 1 == count($allParts) && $filtersCountInUrl == $validFiltersCount;
    }

    private function validatePartsFormatShortSlash(int $basePartsCount, array $result, array $allParts, array $baseParts, string $prefix): bool
    {
        $validFiltersCount = 0;

        $lastBasePart = end($baseParts);
        $lastBasePartIndex = array_search($lastBasePart, $allParts);

        if ($lastBasePartIndex !== false) {
            $filtersCountInUrl = count(array_slice($allParts, (int)$lastBasePartIndex + 1, null, true));
        } else {
            $filtersCountInUrl = 0;
        }
        foreach ($result['params'] as $attr => $item) {
            $options = explode(',', $item);
            $validFiltersCount += count($options);
        }

        if ($prefix) {
            $filtersCountInUrl--;
        }

        return $basePartsCount + $filtersCountInUrl == count($allParts) && $filtersCountInUrl == $validFiltersCount;
    }

    /**
     * @param RewriteInterface|array $rewrite
     * 
     * @return void
     */
    private function checkIfRewriteHasSeparator($rewrite): void
    {
        $separator = $this->urlFormatConfig->getFormat() === ConfigProvider::URL_FORMAT_SHORT_UNDERSCORE
            ? '_' : '-';

        $alias = is_array($rewrite) ? $rewrite['alias'] : trim($rewrite->getRewrite(), $separator);
        if (strpos($alias, $separator) !== false) {
            $attrCode = is_array($rewrite) ? $rewrite['attrCode'] : $rewrite->getAttributeCode();
            $option   = is_array($rewrite) ? $rewrite['option'] : $rewrite->getOption();
            $this->rewritesWithSeparator[$attrCode][$option] = count(explode($separator, $alias));
        }
    }

    private function handleRewritesWithSeparator(array $options, string $attr): int
    {
        if (!empty($this->rewritesWithSeparator && isset($this->rewritesWithSeparator[$attr]))) {
            $dashesCount = 0;
            foreach ($options as $option) {
                if (isset($this->rewritesWithSeparator[$attr][$option])) {
                    $dashesCount += $this->rewritesWithSeparator[$attr][$option] - 1;
                }
            }
            return $dashesCount;
        }
        return 0;
    }
}
