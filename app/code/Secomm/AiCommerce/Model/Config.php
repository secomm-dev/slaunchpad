<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Store-view scoped accessor for LA-22 configuration (SPEC-TASK-QV3R7T §10).
 */
class Config
{
    public const SECTION_PATH = 'seocomm_ai_commerce';
    public const ACL_CONFIG = 'Secomm_AiCommerce::config';

    private const XML_PATH_ENABLED = self::SECTION_PATH . '/general/enabled';
    private const XML_PATH_MAX_PAGE_SIZE = self::SECTION_PATH . '/general/max_page_size';
    private const XML_PATH_FILTER_ALLOWLIST = self::SECTION_PATH . '/general/filter_allowlist';
    private const XML_PATH_CATEGORY_DEPTH = self::SECTION_PATH . '/general/category_depth';
    private const XML_PATH_CACHE_LIFETIME = self::SECTION_PATH . '/cache/lifetime';

    private const HARD_MAX_PAGE_SIZE = 50;
    private const HARD_MAX_CATEGORY_DEPTH = 10;

    /**
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Whether the /ai/* read endpoints are enabled for a store view.
     *
     * @param int|null $storeId store view scope
     * @return bool true when enabled
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Effective server-side page_size cap (bounded to the hard 50 limit).
     *
     * @param int|null $storeId store view scope
     * @return int maximum page size
     */
    public function getMaxPageSize(?int $storeId = null): int
    {
        $max = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_PAGE_SIZE, ScopeInterface::SCOPE_STORE, $storeId);

        if ($max < 1) {
            $max = self::HARD_MAX_PAGE_SIZE;
        }

        return min($max, self::HARD_MAX_PAGE_SIZE);
    }

    /**
     * Allowed filterable attribute codes for /ai/catalog/search.
     *
     * @param int|null $storeId store view scope
     * @return string[] allowed attribute codes
     */
    public function getFilterAllowlist(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::XML_PATH_FILTER_ALLOWLIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $codes = array_map(
            static fn (string $code): string => strtolower(trim($code)),
            explode(',', $raw)
        );

        return array_values(array_unique(array_filter($codes, static fn (string $code): bool => $code !== '')));
    }

    /**
     * Effective category tree depth bound (bounded to the hard 10 limit).
     *
     * @param int|null $storeId store view scope
     * @return int maximum depth below the store root
     */
    public function getCategoryDepth(?int $storeId = null): int
    {
        $depth = (int) $this->scopeConfig->getValue(
            self::XML_PATH_CATEGORY_DEPTH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($depth < 1) {
            $depth = 5;
        }

        return min($depth, self::HARD_MAX_CATEGORY_DEPTH);
    }

    /**
     * Response cache lifetime in seconds (never negative).
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
}
