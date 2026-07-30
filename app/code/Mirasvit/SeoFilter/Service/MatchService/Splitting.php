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

namespace Mirasvit\SeoFilter\Service\MatchService;

use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Model\Context;
use Mirasvit\SeoFilter\Service\RewriteService;
use Mirasvit\SeoFilter\Service\UrlService;
use Mirasvit\SeoFilter\Service\CacheService;

class Splitting
{
    const BRAND_PAGE   = 'brandPage';
    const LANDING_PAGE = 'landingPage';

    private $rewriteService;

    private $urlService;

    private $configProvider;

    private $context;

    private $urlFormatConfig;

    private $cacheService;

    public function __construct(
        RewriteService $rewriteService,
        UrlService $urlService,
        ConfigProvider $configProvider,
        Context $context,
        CacheService $cacheService
    ) {
        $this->rewriteService  = $rewriteService;
        $this->urlService      = $urlService;
        $this->configProvider  = $configProvider;
        $this->context         = $context;
        $this->cacheService    = $cacheService;
        $this->urlFormatConfig = $configProvider->getUrlFormatConfig();
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @param string $basePath
     *
     * @return array
     */
    public function getFiltersString(string $basePath, string $pageType = ''): array
    {
        $uri = trim($this->context->getRequest()->getOriginalPathInfo(), '/');

        $suffix = $this->urlService->getCategoryUrlSuffix();

        // Mirasvit\Brand\Model\Config\GeneralConfig::BRAND_URL_SUFFIX_CUSTOM = 3
        if ($pageType === self::BRAND_PAGE && $this->configProvider->getBrandsUrlSuffixMode() === '3') {
            $suffix = $this->configProvider->getBrandsUrlSuffix();
        }

        if ($pageType === self::LANDING_PAGE) {
            $suffix = $this->configProvider->getLandingPageUrlSuffix();
        }

        if ($suffix && substr($uri, -strlen($suffix)) === $suffix) {
            $uri = substr($uri, 0, -strlen($suffix));
        }

//        $filtersString = trim(str_replace($basePath, '', $uri), '/');

        $filtersString = '';

        if (strpos($uri, $basePath) === 0) {
            $filtersString = trim(substr($uri, strlen($basePath)), '/');
        }


        $prefix = $this->configProvider->getPrefix();
        if ($prefix && substr($filtersString, 0, strlen($prefix)) === $prefix) {
            $filtersString = trim(substr($filtersString, strlen($prefix)), '/');
        }

        if ($this->configProvider->getUrlFormat() == ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
            $result = []; 

            if (ConfigProvider::URL_FORMAT_LONG_DASH == $this->urlFormatConfig->getFormat()) {
                $filtersString = $this->prepareFilterForLongDashFormat($filtersString);
            }

            $filterInfo = explode('/', $filtersString);
            if (ConfigProvider::URL_FORMAT_LONG_COLON == $this->urlFormatConfig->getFormat()) {
                $result = $this->getLongColonFormatFilters($filterInfo);
            } else {
                
                for ($i = 0; $i <= count($filterInfo) - 2; $i += 2) {
                    $attributeAlias = (string)$filterInfo[$i];
                    $rewrite        = $this->rewriteService->getAttributeRewriteByAlias($attributeAlias);
                    $attributeCode  = $rewrite ? $rewrite->getAttributeCode() : $attributeAlias;

                    if ($this->context->isDecimalAttribute($attributeCode)) {
                        $result[$attributeCode][] = $filterInfo[$i + 1];
                    } else {
                        foreach ($this->splitFiltersString($filterInfo[$i + 1]) as $opt) {
                            $result[$attributeCode][] = $opt;
                        }
                    }
                }
            }
        } else {
            $result     = [];
            $filterInfo = explode('/', $filtersString);

            foreach ($filterInfo as $part) {
                foreach ($this->splitFiltersString($part) as $opt) {
                    $result['*'][] = $opt;
                }
            }
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function splitFiltersString(string $filtersString, ?int $storeId = null, ?string $attributeCode = null): array
    {
        $staticFilters = [
            ConfigProvider::LABEL_SALE_NO,
            ConfigProvider::LABEL_SALE_YES,
            ConfigProvider::FILTER_NEW . '_no',
            ConfigProvider::FILTER_NEW . '_yes',
            ConfigProvider::LABEL_RATING_1,
            ConfigProvider::LABEL_RATING_2,
            ConfigProvider::LABEL_RATING_3,
            ConfigProvider::LABEL_RATING_4,
            ConfigProvider::LABEL_RATING_5,
            ConfigProvider::LABEL_STOCK_IN,
            ConfigProvider::LABEL_STOCK_OUT,
        ];

        $separator = ConfigProvider::SEPARATOR_FILTERS;
        $isShortUnderscore = $this->urlFormatConfig->getFormat() == ConfigProvider::URL_FORMAT_SHORT_UNDERSCORE;

        if (
            $this->urlFormatConfig->getFormat() == ConfigProvider::URL_FORMAT_SHORT_SLASH
            || $isShortUnderscore
        ) {
            $separator = $this->urlFormatConfig->getAttributeSeparator();
        }

        $filterInfo = explode($separator, $filtersString);
        $result = [];
        $i = 0;

        $storeIdResolved = $storeId ?? $this->context->getStoreId();
        $rewriteMap = $this->buildRewriteMap($filterInfo, $separator, $storeIdResolved, $attributeCode);

        while ($i < count($filterInfo)) {
            $matched = false;
            $maxMatch = '';
            $maxJ = $i;

            // if price- or range-filter - take it as is
            if ($this->isRangeFilter($filterInfo[$i])) {
                $result[] = $filterInfo[$i];
                $i++;
                continue;
            }

            // Longest-match first
            for ($j = $i; $j < count($filterInfo); $j++) {
                $attemptParts = array_slice($filterInfo, $i, $j - $i + 1);
                $attempt = implode($separator, $attemptParts);

                if ($isShortUnderscore && $this->matchesSliderFilterAlias($attempt)) {
                    continue;
                }

                // Only check isRangeFilter for single elements
                $isSingleElement = ($j === $i);

                if (
                    in_array($attempt, $staticFilters)
                    || ($isSingleElement && $this->isRangeFilter($attempt))
                    || isset($rewriteMap[$attempt])
                ) {
                    $maxMatch = $attempt;
                    $maxJ = $j;
                    $matched = true;
                }
            }

            if ($matched) {
                $result[] = $maxMatch;
                $i = $maxJ + 1;
            } else {
                $i++;
            }
        }

        return $result;
    }

    private function buildRewriteMap(array $filterInfo, string $separator, int $storeId, ?string $attributeCode): array
    {
        $combinations = [];
        $count = count($filterInfo);
        for ($i = 0; $i < $count; $i++) {
            $accumulated = '';
            for ($j = $i; $j < $count; $j++) {
                $accumulated = $j === $i ? $filterInfo[$j] : $accumulated . $separator . $filterInfo[$j];
                $combinations[] = $accumulated;
            }
        }

        $combinations = array_values(array_unique($combinations));

        if (empty($combinations)) {
            return [];
        }

        return $this->rewriteService->getOptionRewritesBatch($combinations, $storeId);
    }

    private function isRangeFilter(string $value): bool
    {
        return preg_match('#^[a-z0-9_]+:[0-9]+(:[0-9]+)?$#i', $value) // price:from:to
            || preg_match('#^\d+(\.\d*)?-\d+(\.\d*)?$#', $value);     // numeric range like 10-99
    }

    private function matchesSliderFilterAlias(string $attempt): bool
    {
        $storeId = $this->context->getStoreId();

        $cacheKey = 'slider_attribute_aliases';
        $sliderAliases = $this->cacheService->getCache($cacheKey, [$storeId]);
        if ($sliderAliases === null) {
            $sliderAliases = [];

            $sliderAttributeCodes = $this->configProvider->getDecimalAttributeCodes();

            foreach ($sliderAttributeCodes as $attributeCode) {
                $rewrite = $this->rewriteService->getAttributeRewrite($attributeCode, $storeId);
                if ($rewrite && $rewrite->getRewrite()) {
                    $sliderAliases[] = preg_quote($rewrite->getRewrite(), '#');
                }
                
            }

            $this->cacheService->setCache($cacheKey, [$storeId], [$sliderAliases]);
        }

        if (empty($sliderAliases)) {
            return false;
        }

        $aliasesPattern = implode('|', $sliderAliases);
       
        $pattern = '#(' . $aliasesPattern . '):\d+(?::\d+)?#i';

        return preg_match($pattern, $attempt) === 1;
    }

    private function prepareFilterForLongDashFormat(string $filterInfo): string
    {
        $result = [];
        $filtersData = explode('/', $filterInfo);
        foreach ($filtersData as $filterData) {

            $filterLine = explode($this->urlFormatConfig->getOptionSeparator(), $filterData);
            $attributeAlias = '';
            foreach ($filterLine as $key => $item) {
                $attributeAlias .= $item;
                if ($attributeRewrite = $this->rewriteService->getAttributeRewriteByAlias($attributeAlias, $this->context->getStoreId())) {
                    $result[] = $attributeRewrite->getRewrite() . '/' . implode('-', array_slice($filterLine, $key + 1, null, true));
                    break;
                }
                $attributeAlias .= '-';
            }
        }

        return implode('/', $result);
    }

    private function getLongColonFormatFilters(array $filterInfo): array
    {      
        $result = [];
          
        foreach ($filterInfo as $item) {
            $filterData = explode($this->urlFormatConfig->getAttributeSeparator(), $item);
            foreach ($filterData as $key => $filter) {
                if ($key == 0) {
                    continue;
                }
                $attributeAlias = (string)$filterData[0];
                $rewrite        = $this->rewriteService->getAttributeRewriteByAlias($attributeAlias);
                $attributeCode  = $rewrite ? $rewrite->getAttributeCode() : $attributeAlias;

                if ($this->context->isDecimalAttribute($attributeCode)) {
                    $result[$attributeCode][] = $filter;
                } else {
                    $filter = str_replace(',', '-', $filter);
                    foreach ($this->splitFiltersString($filter) as $opt) {
                        $result[$attributeCode][] = $opt;
                    }
                }
            }
        }
        return $result;
    }
}
