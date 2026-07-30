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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Model\Context;
use Mirasvit\SeoFilter\Service\MatchService\Splitting;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\Module\Manager;

class FriendlyUrlService
{
    const QUERY_FILTERS = ['cat'];

    private $rewriteService;

    private $urlService;

    private $splitting;

    private $context;

    private $configProvider;

    private $storeManager;

    private $attrAliasInstance = [
        'attrCode'  => null,
        'attrAlias' => null
    ];
    
    private $categoryRewriteCache = [];

    /** @var \Magento\Framework\App\RequestInterface */
    private $request;

    private $urlInstance;

    private $urlFinder;

    private $moduleManager;

    private $urlFormatConfig;

    public function __construct(
        RequestInterface      $request,
        RewriteService        $rewrite,
        UrlService            $urlService,
        Splitting             $splitting,
        UrlInterface          $urlInstance,
        UrlFinderInterface    $urlFinder,
        ConfigProvider        $configProvider,
        Context               $context,
        Manager               $moduleManager,
        StoreManagerInterface $storeManager
    ) {
        $this->request         = $request;
        $this->rewriteService  = $rewrite;
        $this->urlService      = $urlService;
        $this->splitting       = $splitting;
        $this->context         = $context;
        $this->configProvider  = $configProvider;
        $this->urlInstance     = $urlInstance;
        $this->urlFinder       = $urlFinder;
        $this->moduleManager   = $moduleManager;
        $this->storeManager    = $storeManager;
        $this->urlFormatConfig = $configProvider->getUrlFormatConfig();
    }

    public function getUrl(string $attributeCode, string $filterValue, bool $remove = false, ?string $currentUrl = null, ?int $storeId = null): string
    {
        $values = explode(ConfigProvider::SEPARATOR_FILTER_VALUES, $filterValue);

        $requiredFilters[$attributeCode] = [];
        if ($attributeCode != '') {
            foreach ($values as $value) {
                $requiredFilters[$attributeCode][$value] = $value;
            }
        }

        // merge with previous filters
        foreach ($this->rewriteService->getActiveFilters() as $attr => $filters) {
            if (!$this->configProvider->isMultiselectEnabled(($attributeCode == 'cat') ? 'category_ids' : $attributeCode) && $attr == $attributeCode) {
                continue;
            }

            foreach ($filters as $filter) {
                if ($filter == $filterValue) {
                    unset($requiredFilters[$attr][$filter]);
                    continue;
                }

                $requiredFilters[$attr][$filter] = $filter;
            }
        }

        if (isset($requiredFilters['q']) && $this->request->getParam('q', false)) {
            unset($requiredFilters['q']);
        }
        // remove filter
        if ($attributeCode != '') {
            if ($remove && isset($requiredFilters[$attributeCode])) {
                foreach ($values as $value) {
                    if ($value == 'all') {
                        unset($requiredFilters[$attributeCode]);
                        break;
                    }

                    unset($requiredFilters[$attributeCode][$value]);
                }
            }
        }
        // merge all filters on one line f1-f2-f3-f4
        $filterLines = [];
        $queryParams = [];

        foreach ($requiredFilters as $attrCode => $filters) {
            if ($this->attrAliasInstance['attrCode'] === $attrCode) {
                $attrAlias = $this->attrAliasInstance['attrAlias'];
            } else {
                $attrAlias  = $this->getAttributeRewrite($attrCode);
                $this->attrAliasInstance['attrAlias'] = $attrAlias;
                $this->attrAliasInstance['attrCode'] = $attrCode;
            }
            $filterLine = [];
            $queryParam = [];

            foreach ($filters as $filter) {
                if (in_array($attrCode, self::QUERY_FILTERS) || !$this->configProvider->isAttributeEnabled($attrCode)) {
                    $queryParam[] = $filter;
                } else {
                    $filterLine[] = $this->rewriteService->getOptionRewrite($attrCode, $filter, $storeId)->getRewrite();
                }
            }

            if (in_array($attrCode, self::QUERY_FILTERS) && count($filters) == 0) {
                $queryParam[] = '';
            }

            if (count($queryParam)) {
                $queryParams[$attrAlias] = implode(',', $queryParam);
            }

            if (count($filterLine)) {
                $filterLine = $this->sortOptions($filterLine);
                $separator = $this->urlFormatConfig->getOptionSeparator();
                $filterLines[$attrAlias] = implode($separator, $filterLine);
            }
        }
        
        if ($this->configProvider->getUrlFormat() === ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
            foreach ($filterLines as $attr => $options) {
                $filterLines[$attr] = $attr . $this->urlFormatConfig->getAttributeSeparator() . $options;
            }
            ksort($filterLines);

            $filterString = implode('/', $filterLines);
        } else {
            $filterLines = implode($this->urlFormatConfig->getAttributeSeparator(), $filterLines);
            //sort filters
            $values = $this->splitting->splitFiltersString($filterLines, $storeId, $attributeCode);

            if (is_array($values)) {
                $values = $this->sortOptions($values);
            }
            $filterString = implode($this->urlFormatConfig->getAttributeSeparator(), $values);
        }

        //add extra query params
        foreach ($this->urlService->getGetParams() as $param => $value) {
            if (!array_key_exists($param, $requiredFilters)) {
                if ($param === 'p' || ($remove && !$this->configProvider->isAttributeEnabled($attributeCode) && $param == $attributeCode)) {
                    continue;
                }

                $queryParams[$param] = $value;
            }
        }
        
        return $this->getPreparedCurrentUrl($filterString, $queryParams, $currentUrl);
    }

    public function getPreparedCurrentUrl(string $filterUrlString, array $queryParams, ?string $currentUrl = null): string
    {
        $suffix = $this->getSuffix((bool)$currentUrl);
        $url    = $currentUrl ? : $this->getClearUrl();
        $url    = (string)preg_replace('/\?.*/', '', $url);
        $url    = ($suffix && $suffix !== '/') ? str_replace($suffix, '', $url) : $url;
        if (!empty($filterUrlString)) {
            if ($separator = $this->configProvider->getPrefix()) {
                $url .= (substr($url, -1, 1) === '/' ? '' : '/') . $separator;
            }

            $url .= (substr($url, -1, 1) === '/' ? '' : '/') . $filterUrlString;
        }

        $url = $url . $suffix;
        $url = preg_replace("@//$@", "/", $url);

        $query = '';
        if (count($queryParams)) {
            $query = '?' . http_build_query($queryParams);
        }

        return $url . $query;
    }

    public function getClearUrl(): string
    {
        $url = '';

        $fullActionName = $this->request->getFullActionName();
        switch ($fullActionName) {
            case 'catalog_category_view':
                $category = $this->context->getCurrentCategory();
                $cacheKey = $category->getId() . '_' . $this->context->getStoreId();

                if (isset($this->categoryRewriteCache[$cacheKey])) {
                    $rewrite = $this->categoryRewriteCache[$cacheKey];
                } else {
                    // need this because $category->getUrl() can return not direct URL (rewrite with redirect)
                    // we need to be sure that we get the direct category URL
                    $rewrite = $this->urlFinder->findOneByData(
                        [
                            'entity_id'     => $category->getId(),
                            'entity_type'   => 'category',
                            'store_id'      => $this->context->getStoreId(),
                            'redirect_type' => 0,
                        ]
                    );
                    $this->categoryRewriteCache[$cacheKey] = $rewrite;
                }

                if ($rewrite) {
                    $category->setData('url', $this->urlInstance->getDirectUrl($rewrite->getRequestPath()));
                }

                $url = $category->getUrl();
                break;

            case 'all_products_page_index_index':
                $url = ObjectManager::getInstance()->get('\Mirasvit\AllProducts\Service\UrlService')->getClearUrl();
                break;

            case 'brand_brand_view':
                $url = ObjectManager::getInstance()->get('Mirasvit\Brand\Service\BrandUrlService')->getBaseBrandUrl();

                $currentUrl  = (string)parse_url($this->request->getRequestString(), PHP_URL_PATH);

                $suffix = $this->getSuffix();

                if ($suffix && $suffix != '/' && $this->endsWith($currentUrl, $suffix)) {
                    $currentUrl = (string)preg_replace('/'. preg_quote($suffix, '/') . '$/', '', $currentUrl);
                }

                /** @var \Mirasvit\Brand\Repository\BrandRepository|object $brandRepository */
                $brandRepository = ObjectManager::getInstance()->get('Mirasvit\Brand\Repository\BrandRepository');

                foreach ($brandRepository->getFullList() as $brand) {
                    if (preg_match('/\/' . $brand->getUrlKey() . '\//', rtrim($currentUrl, '/') . '/')) {
                        $url = $brand->getUrl();
                        break;
                    }
                }

                break;
            case 'landing_landing_view':
                $landingId = $this->request->getParam('landing');
                if (!$landingId) {
                    return $url;
                }

                $storeId = (int)$this->storeManager->getStore()->getId();

                $landing = ObjectManager::getInstance()->get('Mirasvit\LandingPage\Repository\PageRepository')->get((int)$landingId, $storeId);

                if (!$landing || !$landing->getUrlKey()) {
                    return $url;
                }

                $landingStoreCode = $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_LINK, true);

                $suffix = $this->getSuffix();
                $suffix = ($suffix && $suffix !== '/') ? $suffix : '';

                $url = $landingStoreCode . ltrim($landing->getUrlKey(), '/') . $suffix;
                break;
        }

        return $url;
    }

    private function endsWith(string $haystack, string $needle): bool 
    {
        $length = strlen($needle);
        if(!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    public function getSuffix(bool $isCategory = false): string
    {
        $suffix = '';
        if ($this->request->getFullActionName() == 'catalog_category_view' || $isCategory) {
            $suffix = $this->urlService->getCategoryUrlSuffix();
        }

        if ($this->request->getFullActionName() == 'brand_brand_view') {
            $brandConfig = ObjectManager::getInstance()->get('Mirasvit\Brand\Model\Config\GeneralConfig');
            $suffix = $brandConfig->getUrlSuffix() ?? '';
        }

        if ($this->request->getFullActionName() == 'landing_landing_view') {
            $suffix = $this->configProvider->getLandingPageUrlSuffix();
        }

        return $suffix;
    }

    public function getAttributeRewrite(string $attributeCode): string
    {
        $attrRewrite = $this->rewriteService->getAttributeRewrite($attributeCode);

        return $attrRewrite ? $attrRewrite->getRewrite() : $attributeCode;
    }

    public function getUrlWithFilters(string $url, array $filters): string
    {
        $filterString = '';
        $filterLines  = [];

        foreach ($filters as $code => $options) {
            $code = strval($code);
            $attrAlias = $this->getAttributeRewrite($code);

            $filterLine = [];

            foreach (explode(',', $options) as $option) {
                $optionRewrite = $this->rewriteService->getOptionRewrite($code, $option);

                if ($optionRewrite) {
                    $filterLine[] = $optionRewrite->getRewrite();
                }
            }

            $filterLines[$attrAlias] = implode($this->urlFormatConfig->getOptionSeparator(), $filterLine);
        }

        if ($this->configProvider->getUrlFormat() === ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
            foreach ($filterLines as $attr => $options) {
                $filterLines[$attr] = $attr . $this->urlFormatConfig->getAttributeSeparator() . $options;
            }
            ksort($filterLines);

            $filterString = implode('/', $filterLines);
        } else {
            $filterLines = implode($this->urlFormatConfig->getAttributeSeparator(), $filterLines);

            //sort filters
            $values = $this->splitting->splitFiltersString($filterLines);

            if (is_array($values)) {
                $values = $this->sortOptions($values);
            }

            $filterString = implode($this->urlFormatConfig->getAttributeSeparator(), $values);
        }

        return $this->getPreparedCurrentUrl($filterString, [], $url);
    }

    private function sortOptions(array $filterOptions): array
    {
        uasort($filterOptions, function ($a, $b) {
            if ($a == $b) {
                return 0;
            }
            // sorting for options like a-b and a-b-a with dash separator
            if (substr($a, 0, strlen($b)) === $b) {
                return -1;
            }
            if (substr($b, 0, strlen($a)) === $a) {
                return 1;
            }
            return ($a < $b) ? -1 : 1;
            }
        );

        return $filterOptions;
    }

    public function getSliderUrl(FilterInterface $filter, string $template): string
    {
        $activeFilters = $this->rewriteService->getActiveFilters();

        $priceSeparator = ConfigProvider::SEPARATOR_DECIMAL;
        if ($this->configProvider->getUrlFormat() === ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
            $priceSeparator = $this->urlFormatConfig->getAttributeSeparator();
        }

        $urlBuilder = $this->context->getUrlBuilder();
        $currentUrl = $urlBuilder->getCurrentUrl();
        
        if ($currentUrl === $urlBuilder->getBaseUrl()) {
            $currentUrl = $this->getClearUrl();
        }
        
        $queryParams = [];
        $queryParamsStr = parse_url($currentUrl);
        if (isset($queryParamsStr['query'])) {
            parse_str($queryParamsStr['query'], $queryParams);
            unset($queryParams['isAjax']);
            unset($queryParams['is_scroll']);
        }

        $storeId = (int)$this->storeManager->getStore()->getStoreId();

        $suffix = $this->urlService->getCategoryUrlSuffix($storeId);
        $suffix = $suffix && strpos($currentUrl, $suffix) !== false ? $suffix : '';

        if(
            $this->request->getModuleName() == 'brand' 
            && $this->moduleManager->isEnabled('Mirasvit_Brand') 
            // Mirasvit\Brand\Model\Config\GeneralConfig::BRAND_URL_SUFFIX_CUSTOM = 3
            && $this->configProvider->getBrandsUrlSuffixMode() === '3'
        ) {
            $suffix = $this->configProvider->getBrandsUrlSuffix();
        } elseif (
            $this->request->getModuleName() == 'landing' 
            && $this->moduleManager->isEnabled('Mirasvit_LandingPage')
        ) {
            $suffix = $this->configProvider->getLandingPageUrlSuffix();
        }

        $currentUrl = $this->removeCategorySuffix($currentUrl, $suffix);
        
        $rewrite        = $this->rewriteService->getAttributeRewrite($filter->getRequestVar());
        $attributeAlias = $rewrite ? $rewrite->getRewrite() : $filter->getRequestVar();

        $price = $attributeAlias . $priceSeparator . $template;
        
        if (isset($activeFilters[$attributeAlias]) || isset($activeFilters[$filter->getRequestVar()])) {
            $parsedUrl = parse_url($currentUrl);
            $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
            $path = explode('/', $path);
            
            if ($this->configProvider->getUrlFormat() === ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
                $path = $this->handlePathForLongFormat($attributeAlias, $path);
            } elseif($this->urlFormatConfig->getFormat() === ConfigProvider::URL_FORMAT_SHORT_SLASH) {
                if ($filter->getRequestVar() == 'price') {
                    $attributeAlias = 'price';
                }
                $needle  = str_replace('-', ':', array_key_last($activeFilters[$rewrite->getAttributeCode()]));
                $needle  = str_replace($template, $needle, $price);
                $entry   = array_search($needle, $path);
                if ($entry !== false) {
                    unset($path[$entry]);
                }
            } else {
                if ($filter->getRequestVar() == 'price') {
                    $attributeAlias = 'price';
                }
                
                $key     = array_key_last($path);
                $filters = explode($this->urlFormatConfig->getOptionSeparator(), $path[$key]);
                $needle  = str_replace('-', ':', array_key_last($activeFilters[$rewrite->getAttributeCode()]));
                $needle  = str_replace($template, $needle, $price);
                $entry   = array_search($needle, $filters);
                
                if ($entry !== false) {
                    unset($filters[$entry]);
                }

                $filters = array_filter($filters, function ($filter) {
                    return !!trim($filter);
                });

                $path[$key] = implode($this->urlFormatConfig->getOptionSeparator(), $filters);
            }
            $path       = implode('/', $path);
            $parsedCurrentUrl = parse_url($currentUrl);
            $currentUrl = str_replace(isset($parsedCurrentUrl['path']) ? $parsedCurrentUrl['path'] : '', $path, $currentUrl);
        }

        $originPath = isset(parse_url($currentUrl)['path']) ? parse_url($currentUrl)['path'] : '';
        $path = explode('/', $originPath);
        
        if (empty($activeFilters) && ($prefix = $this->configProvider->getPrefix())) {
            $path[] = trim($prefix, '/'); 
        }
        
        $isPriceAttributeSeoEnabled = $this->configProvider->isAttributeEnabled('price');
        if (!$isPriceAttributeSeoEnabled) {
            $queryParams['price'] = $template;
        } else {
            if (
                $this->urlFormatConfig->getFormat() !== ConfigProvider::URL_FORMAT_SHORT_DASH
                && $this->urlFormatConfig->getFormat() !== ConfigProvider::URL_FORMAT_SHORT_UNDERSCORE
            ) {
                $path[] = $price;
            } else {
                foreach ($queryParams as $paramName => $paramValue) {
                    if (isset($activeFilters[$paramName])) {
                        unset($activeFilters[$paramName]);
                    }
                }
                if (empty($activeFilters)) {
                    $path[] = $price;
                } else {
                    $key     = array_key_last($path);
                    $filters = explode($this->urlFormatConfig->getOptionSeparator(), $path[$key]);
                    $filters = array_filter($filters, function ($filter) {
                        return !!trim($filter);
                    });
                    $filters[]  = $price;
                    $filters    = implode($this->urlFormatConfig->getAttributeSeparator(), $filters);
                    $path[$key] = $filters;
                }
            }
        }
        $path       = implode('/', $path);
        $currentUrl = str_replace($originPath, $path, $currentUrl);
        $currentUrl = $currentUrl . $suffix;

        if (count($queryParams)) {
            $currentUrl .= '?' . http_build_query($queryParams);
        }
        return $currentUrl;
    }

    private function handlePathForLongFormat(string $attributeAlias, array $path): array
    {
        if ($this->urlFormatConfig->getFormat() !== ConfigProvider::URL_FORMAT_LONG_SLASH) {
            foreach ($path as $key => $item) {
                $filter = explode($this->urlFormatConfig->getAttributeSeparator(), $item);
                $entry = array_search($attributeAlias, $filter);

                if ($entry !== false) {
                    unset($path[$key]);
                    return $path;
                }
            }
        }
        
        $entry = array_search($attributeAlias, $path);

        if ($entry !== false) {
            unset($path[(int)$entry + 1]);
            unset($path[(int)$entry]);
        }

        return $path;
    }

    private function removeCategorySuffix(string $path, string $categorySuffix): string
    {
        if (!$categorySuffix) {
            return $path;
        }

        $suffixPosition = strrpos($path, $categorySuffix);

        return $suffixPosition !== false
            ? substr($path, 0, $suffixPosition)
            : $path;
    }
}
