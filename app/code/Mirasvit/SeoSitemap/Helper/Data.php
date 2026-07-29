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



namespace Mirasvit\SeoSitemap\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Service\RewriteService;
use Mirasvit\SeoSitemap\Model\Config;
use Mirasvit\SeoSitemap\Model\Config\LinkSitemapConfig;
use Mirasvit\SeoSitemap\Service\SeoSitemapUrlService;

class Data extends AbstractHelper
{
    private $config;

    private $seoSitemapUrlService;

    private $linkSitemapConfig;

    private $rewriteService;

    private $storeManager;

    private $excludedLinks;

    private $excludedLinksIndex = []; // [storeId => ['exact' => [...], 'wildcard' => [...]]]

    private $excludedRewriteIndex = []; // [storeId => ['exact' => [...], 'wildcard' => [...]]]

    public function __construct(
        Config                $config,
        SeoSitemapUrlService  $seoSitemapUrlService,
        LinkSitemapConfig     $linkSitemapConfig,
        RewriteService        $rewriteService,
        StoreManagerInterface $storeManager,
        Context               $context
    ) {
        $this->config               = $config;
        $this->seoSitemapUrlService = $seoSitemapUrlService;
        $this->linkSitemapConfig    = $linkSitemapConfig;
        $this->rewriteService       = $rewriteService;
        $this->storeManager         = $storeManager;

        parent::__construct($context);
    }

    /**
     * @return string
     */
    public function getSitemapTitle()
    {
        return $this->config->getFrontendSitemapH1();
    }

    /**
     * @return string
     */
    public function getSitemapUrl()
    {
        return $this->seoSitemapUrlService->getBaseUrl();
    }

    /**
     * @param string     $stringVal
     * @param array      $patternArr
     * @param bool|false $caseSensativeVal
     *
     * @return bool
     */
    public function checkArrayPattern($stringVal, $patternArr, $caseSensativeVal = false)
    {
        if (!is_array($patternArr)) {
            return false;
        }
        foreach ($patternArr as $patternVal) {
            if ($this->checkPattern($stringVal, $patternVal, $caseSensativeVal)) {
                return true;
            }
        }

        return false;
    }

    public function checkIsUrlExcluded(string $url, $store = null): bool
    {
        if ($this->isInExcludedLinks($url, $store)) {
            return true;
        }

        if ($this->isInExcludedRewrite($url, $store)) {
            return true;
        }

        return false;
    }

    public function getExcludedLinks($store = null): array
    {
        if ($store instanceof StoreInterface) {
            $store = $store->getId();
        }

        if (!$store) {
            $store = 0;
        }

        if (!isset($this->excludedLinks[$store])) {
            $this->excludedLinks[$store] = $this->linkSitemapConfig->getExcludeLinks($store);
        }

        return $this->excludedLinks[$store];
    }

    /**
     * @param string $urlWithHost
     *
     * @return mixed|string
     */
    public function removeHostUrl($urlWithHost)
    {
        $parts = parse_url($urlWithHost);
        if (!is_array($parts)) {
            return $urlWithHost;
        }
        $url = $parts['path'] ?? '';
        $url   = str_replace('index.php/', '', $url);
        $url   = str_replace('index.php', '', $url);

        if (strpos($url, '/') !== 0) {
            $url = '/' . $url; //need this so exclude patterns will work the same way for Frontend and XML sitemaps
        }

        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        return $url;
    }

    /**
     * @param string     $url
     * @param string     $pattern
     * @param bool|false $caseSensative
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function checkPattern($url, $pattern, $caseSensative = false)
    {
        $string = $this->removeHostUrl($url);

        if (!$caseSensative) {
            $string  = strtolower($string);
            $pattern = strtolower($pattern);
        }

        if (strpos($pattern, '*') === false) {
            return rtrim($string, '/') === rtrim($pattern, '/');
        }

        $parts = explode('*', $pattern);
        $index = 0;

        $shouldBeFirst = true;

        foreach ($parts as $part) {
            if ($part == '') {
                $shouldBeFirst = false;
                continue;
            }

            $index = strpos($string, $part, $index);

            if ($index === false) {
                return false;
            }

            if ($shouldBeFirst && $index > 0) {
                return false;
            }

            $shouldBeFirst = false;
            $index         += strlen($part);
        }

        if (count($parts) == 1) {
            return $string == $pattern;
        }

        $last = end($parts);
        if ($last == '') {
            return true;
        }

        if (strrpos($string, $last) === false) {
            return false;
        }

        if (strlen($string) - strlen($last) - strrpos($string, $last) > 0) {
            return false;
        }

        return true;
    }

    public function isInExcludedRewrite(string $url, $store = null): bool
    {
        $index = $this->getExcludedRewriteIndex($store);

        if (empty($index['exact']) && empty($index['wildcard'])) {
            return false;
        }

        $normalizedUrl = ltrim($url, '/');

        if (isset($index['exact'][$normalizedUrl])) {
            return true;
        }

        foreach ($index['wildcard'] as $pattern) {
            if ($this->matchWildcardPattern($normalizedUrl, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isInExcludedLinks(string $url, $store = null): bool
    {
        $index = $this->getExcludedLinksIndex($store);

        if (empty($index['exact']) && empty($index['wildcard'])) {
            return false;
        }

        $normalizedUrl = strtolower(rtrim($this->removeHostUrl($url), '/'));

        if (isset($index['exact'][$normalizedUrl])) {
            return true;
        }

        foreach ($index['wildcard'] as $pattern) {
            if ($this->checkPattern($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function getExcludedLinksIndex($store): array
    {
        $storeId = $this->storeManager->getStore($store)->getId();

        if (isset($this->excludedLinksIndex[$storeId])) {
            return $this->excludedLinksIndex[$storeId];
        }

        $exact    = [];
        $wildcard = [];

        foreach ($this->getExcludedLinks($storeId) as $pattern) {
            if (strpos($pattern, '*') !== false) {
                $wildcard[] = $pattern;
            } else {
                $exact[strtolower(rtrim($pattern, '/'))] = true;
            }
        }

        $this->excludedLinksIndex[$storeId] = ['exact' => $exact, 'wildcard' => $wildcard];

        return $this->excludedLinksIndex[$storeId];
    }

    private function getExcludedRewriteIndex($store): array
    {
        $store = $this->storeManager->getStore($store);
        $storeId  = $store->getId();

        if (isset($this->excludedRewriteIndex[$storeId])) {
            return $this->excludedRewriteIndex[$storeId];
        }

        $includeRobots = [
            RewriteInterface::META_ROBOTS_DEFAULT,
            RewriteInterface::META_ROBOTS_INDEX_FOLLOW,
        ];

        $storeCode = $store->getCode();
        $exact     = [];
        $wildcard  = [];

        foreach ($this->rewriteService->getRewriteCollection($store) as $rewrite) {
            $isExcluded = $rewrite->getAddToSitemap() == RewriteInterface::SITEMAP_NO
                || ($rewrite->getAddToSitemap() == RewriteInterface::SITEMAP_DEFAULT
                    && !in_array($rewrite->getMetaRobots(), $includeRobots));

            if (!$isExcluded) {
                continue;
            }

            $rawUrl = ltrim($rewrite->getUrl(), '/');

            if (strpos($rawUrl, '*') !== false) {
                $wildcard[] = $rawUrl;
                if (strpos($rawUrl, $storeCode . '/') !== 0) {
                    $wildcard[] = $storeCode . '/' . $rawUrl;
                }
            } else {
                $exact[$rawUrl] = true;
                if (strpos($rawUrl, $storeCode . '/') !== 0) {
                    $exact[$storeCode . '/' . $rawUrl] = true;
                }
            }
        }

        $this->excludedRewriteIndex[$storeId] = ['exact' => $exact, 'wildcard' => $wildcard];

        return $this->excludedRewriteIndex[$storeId];
    }

    private function matchWildcardPattern(string $url, string $pattern): bool
    {
        $url     = strtolower($url);
        $pattern = strtolower($pattern);

        $parts         = explode('*', $pattern);
        $index         = 0;
        $shouldBeFirst = true;

        foreach ($parts as $part) {
            if ($part === '') {
                $shouldBeFirst = false;
                continue;
            }
            $pos = strpos($url, $part, $index);
            if ($pos === false) {
                return false;
            }
            if ($shouldBeFirst && $pos > 0) {
                return false;
            }
            $shouldBeFirst = false;
            $index         = $pos + strlen($part);
        }

        if (count($parts) === 1) {
            return $url === $pattern;
        }

        $last = end($parts);
        if ($last === '') {
            return true;
        }

        $lastPos = strrpos($url, $last);

        return $lastPos !== false && strlen($url) === $lastPos + strlen($last);
    }
}
