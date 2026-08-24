<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Store-view scoped accessor for LC-30 configuration (SPEC-TASK-0X552E §4.3).
 */
class Config
{
    public const SECTION_PATH = 'seocomm_ai_discoverability';
    public const ACL_CONFIG = 'Secomm_AiDiscoverability::config';
    public const ACL_PREVIEW = 'Secomm_AiDiscoverability::preview';

    private const XML_PATH_ENABLED = self::SECTION_PATH . '/general/enabled';
    private const XML_PATH_SITE_TITLE = self::SECTION_PATH . '/general/site_title';
    private const XML_PATH_BRAND_SUMMARY = self::SECTION_PATH . '/general/brand_summary';
    private const XML_PATH_STORE_INFO_NAME = 'general/store_information/name';
    private const XML_PATH_CURRENCY_DEFAULT = 'currency/options/default';
    private const XML_PATH_PRIORITY_PATHS = self::SECTION_PATH . '/general/priority_paths';
    private const XML_PATH_CMS_PAGES = self::SECTION_PATH . '/urls/cms_pages';
    private const XML_PATH_CATEGORIES = self::SECTION_PATH . '/urls/categories';
    private const XML_PATH_INCLUDE_SITEMAP = self::SECTION_PATH . '/urls/include_sitemap_refs';
    private const XML_PATH_CACHE_LIFETIME = self::SECTION_PATH . '/cache/lifetime';
    private const XML_PATH_MAX_URLS = self::SECTION_PATH . '/cache/max_urls';

    /**
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Whether the llms.txt feature is enabled for a store view.
     *
     * @param int|null $storeId store view scope
     * @return bool true when enabled
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Public site/brand title for the llms.txt H1 of a store view.
     *
     * Falls back to the merchant's public store information name before any
     * internal store-view label (SPEC-TASK-0X552E §12.2). The internal
     * `$store->getName()` last resort is applied by the generator.
     *
     * @param int|null $storeId store view scope
     * @return string site title ('' when neither source is configured)
     */
    public function getSiteTitle(?int $storeId = null): string
    {
        $title = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_SITE_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        if ($title !== '') {
            return $title;
        }

        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_STORE_INFO_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    /**
     * Effective default currency code of a store view.
     *
     * Read from store-scoped config (deterministic per store — deliberately
     * not the visitor-switchable current currency, to keep the endpoint
     * byte-deterministic; SPEC-TASK-0X552E §12.4).
     *
     * @param int|null $storeId store view scope
     * @return string ISO currency code ('' when unconfigured)
     */
    public function getCurrencyCode(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_CURRENCY_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    /**
     * Brand summary text configured for a store view.
     *
     * @param int|null $storeId store view scope
     * @return string brand summary
     */
    public function getBrandSummary(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_BRAND_SUMMARY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Internal priority paths, one per line, normalized.
     *
     * @param int|null $storeId store view scope
     * @return string[] normalized priority paths
     */
    public function getPriorityPaths(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PRIORITY_PATHS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }

    /**
     * CMS page ids selected for a store view.
     *
     * @param int|null $storeId store view scope
     * @return int[] selected CMS page ids
     */
    public function getCmsPageIds(?int $storeId = null): array
    {
        return array_map('intval', $this->getArrayValue(self::XML_PATH_CMS_PAGES, $storeId));
    }

    /**
     * Category ids selected for a store view.
     *
     * @param int|null $storeId store view scope
     * @return int[] selected category ids
     */
    public function getCategoryIds(?int $storeId = null): array
    {
        return array_map('intval', $this->getArrayValue(self::XML_PATH_CATEGORIES, $storeId));
    }

    /**
     * Whether sitemap references are included for a store view.
     *
     * @param int|null $storeId store view scope
     * @return bool true when included
     */
    public function isIncludeSitemapRefs(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_PATH_INCLUDE_SITEMAP,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Cache lifetime in seconds (never negative) for a store view.
     *
     * @param int|null $storeId store view scope
     * @return int cache lifetime in seconds
     */
    public function getCacheLifetime(?int $storeId = null): int
    {
        return max(
            0,
            (int) $this->scopeConfig->getValue(
                self::XML_PATH_CACHE_LIFETIME,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        );
    }

    /**
     * Global URL bound for a store view (defaults to 100).
     *
     * @param int|null $storeId store view scope
     * @return int maximum number of emitted URLs
     */
    public function getMaxUrls(?int $storeId = null): int
    {
        $max = (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_URLS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $max > 0 ? $max : 100;
    }

    /**
     * Read a comma-separated or array config value as a trimmed array.
     *
     * @param string $path config path
     * @param int|null $storeId store view scope
     * @return array<int, string> non-empty value parts
     */
    private function getArrayValue(string $path, ?int $storeId): array
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }

        return array_filter(explode(',', (string) $value), static fn ($v) => $v !== '');
    }
}
