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
    private const XML_PATH_ENDPOINT_PATH = self::SECTION_PATH . '/general/endpoint_path';
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

    public const DEFAULT_ENDPOINT_PATH = 'ai';
    private const MAX_ENDPOINT_PATH_LENGTH = 64;
    private const ENDPOINT_PATH_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9\/_-]*$/';

    /**
     * Effective AI read endpoint base path for a store view (normalized).
     *
     * The single normalization authority for the base path: "ai", "/ai",
     * "ai/" and " ai " all resolve to "ai". An empty or invalid value
     * (query string, fragment, protocol URL, traversal, spaces, bare "/")
     * falls back to the default "ai" so the endpoints never stop resolving.
     *
     * @param int|null $storeId store view scope
     * @return string base path without surrounding slashes
     */
    public function getEndpointPath(?int $storeId = null): string
    {
        return self::normalizeEndpointPath((string) $this->scopeConfig->getValue(
            self::XML_PATH_ENDPOINT_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    /**
     * Canonicalize a raw base-path config value.
     *
     * Public so the config backend model reuses the exact same rule at save
     * time — normalization is defined once (SPEC-TASK-0X552E §4.5).
     *
     * @param string $raw raw configured value
     * @return string canonical base path ('ai' when empty/invalid)
     */
    public static function normalizeEndpointPath(string $raw): string
    {
        $path = trim(trim($raw), '/');

        if ($path === ''
            || strlen($path) > self::MAX_ENDPOINT_PATH_LENGTH
            || preg_match(self::ENDPOINT_PATH_PATTERN, $path) !== 1
        ) {
            return self::DEFAULT_ENDPOINT_PATH;
        }

        return $path;
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
