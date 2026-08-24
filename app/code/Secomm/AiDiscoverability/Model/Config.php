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
    private const XML_PATH_BRAND_SUMMARY = self::SECTION_PATH . '/general/brand_summary';
    private const XML_PATH_PRIORITY_PATHS = self::SECTION_PATH . '/general/priority_paths';
    private const XML_PATH_CMS_PAGES = self::SECTION_PATH . '/urls/cms_pages';
    private const XML_PATH_CATEGORIES = self::SECTION_PATH . '/urls/categories';
    private const XML_PATH_INCLUDE_SITEMAP = self::SECTION_PATH . '/urls/include_sitemap_refs';
    private const XML_PATH_CACHE_LIFETIME = self::SECTION_PATH . '/cache/lifetime';
    private const XML_PATH_MAX_URLS = self::SECTION_PATH . '/cache/max_urls';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
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
