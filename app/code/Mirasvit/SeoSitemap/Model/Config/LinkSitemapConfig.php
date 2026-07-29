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



namespace Mirasvit\SeoSitemap\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Model\ResourceModel\Redirect\CollectionFactory as RedirectCollectionFactory;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Api\Repository\RewriteRepositoryInterface;
use Mirasvit\SeoSitemap\Model\Config;

class LinkSitemapConfig
{
    private $scopeConfig;

    private $config;

    private $rewriteRepository;

    private $redirectCollectionFactory;

    private $storeManager;

    public function __construct(
        ScopeConfigInterface       $scopeConfig,
        Config                     $config,
        RewriteRepositoryInterface $rewriteRepository,
        RedirectCollectionFactory  $redirectCollectionFactory,
        StoreManagerInterface      $storeManager
    ) {
        $this->scopeConfig               = $scopeConfig;
        $this->config                    = $config;
        $this->rewriteRepository         = $rewriteRepository;
        $this->redirectCollectionFactory = $redirectCollectionFactory;
        $this->storeManager              = $storeManager;
    }

    public function getAdditionalLinks($store = null, bool $ignoreTitle = false): array
    {
        $conf = (string)$this->scopeConfig->getValue(
            'seositemap/frontend/additional_links',
            ScopeInterface::SCOPE_STORE,
            $store
        );
        $links = [];
        $ar = explode("\n", $conf);
        $ar = array_merge($ar, $this->getSeoRewriteLinks($store));
        foreach ($ar as $v) {
            $p = explode(',', trim($v));
            if ((!isset($p[0]) || !$p[0]) || (!$ignoreTitle && (!isset($p[1]) || !$p[1]))) {
                continue;
            }

            $link['url'] = trim($p[0]);

            if (isset($p[1]) && $p[1]) {
                $link['title'] = trim($p[1]);
            }

            $links[] = new DataObject($link);
        }

        return $links;
    }

    public function getExcludeLinks($store = null): array
    {
        $conf = (string)$this->scopeConfig->getValue(
            'seositemap/frontend/exclude_links',
            ScopeInterface::SCOPE_STORE,
            $store
        );

        $links = explode("\n", trim($conf));
        $links = array_map('trim', $links);

        $links = array_diff($links, [0, null]);

        $links = array_merge($links, $this->getRedirectLinks($store));

        return $links;
    }

    private function getSeoRewriteLinks($store = null): array
    {
        $links = [];

        if ($store instanceof Store) {
            $storeCode = $store->getCode();
        } else {
            $storeCode = $store ? $this->storeManager->getStore($store)->getCode() : null;
        }

        $collection = $this->rewriteRepository->getCollection();
        $collection->addFieldToFilter(RewriteInterface::IS_ACTIVE, true)
            ->addSitemapFilter();

        if ($store) {
            $collection->addStoreFilter($store);
        }

        foreach ($collection as $item) {
            if ((strpos($item->getUrl(), '*') !== false) || (strpos($item->getUrl(), '?') === 0)) {
                continue;
            }

            if (($item->getAddToSitemap() == RewriteInterface::SITEMAP_YES)
                || ($this->config->getIncludeSeoRewrites() && ($item->getMetaRobots() == RewriteInterface::META_ROBOTS_INDEX_FOLLOW))
            ) {
                $url = $item->getUrl();

                if ($storeCode) {
                    $pos = strpos(ltrim($url, '/'), $storeCode . '/');
                    if ($pos === 0) {
                        $url = substr_replace(ltrim($url, '/'), '/', $pos, strlen($storeCode . '/'));
                    }
                }

                $links[] = implode(',', [$url, $item->getTitle()]);
            }
        }

        return $links;
    }

    private function getRedirectLinks($store = null): array
    {
        if ($this->config->getExcludeRedirects($store) == Config::REDIRECTS_EXCLUDE_DISABLE) {
            return [];
        }

        $links = [];

        $collection = $this->redirectCollectionFactory->create();
        $collection->addFieldToSelect('url_from')
            ->addActiveFilter();
        if ($store) {
            $collection->addStoreFilter($store);
        }
        if ($this->config->getExcludeRedirects($store) == Config::REDIRECTS_EXCLUDE_PERMANENT) {
            $collection->addFieldToFilter('is_redirect_only_error_page', 0);
        }

        foreach ($collection as $item) {
            if ((strpos($item->getUrlFrom(), '*') !== false) || (strpos($item->getUrlFrom(), '?') === 0)) {
                continue;
            }

            $links[] = $item->getUrlFrom();
        }

        return $links;
    }
}
