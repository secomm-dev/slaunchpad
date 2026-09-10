<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Secomm\AiDiscoverability\Model\Config;

/**
 * Machine-readable Commerce discovery entries for the Secomm_AiCommerce
 * read-only surface (SPEC-TASK-7FBHHC §2.1).
 *
 * Discovery metadata ONLY: no AiCommerce endpoint is executed and no catalog
 * data is loaded while generating these entries — the section is derived from
 * module presence + config flags. The seam is deliberately soft: no
 * AiCommerce class is referenced (ModuleList + config paths), so DI
 * compilation and generation keep working when Secomm_AiCommerce is absent or
 * disabled, in which case this source yields no entries.
 *
 * Advertised URLs use the AiCommerce base path configured at
 * seocomm_ai_commerce/general/endpoint_path (canonicalized at save time by
 * AiCommerce's backend model; default "ai"). llms.txt therefore advertises
 * the effective endpoint URLs, never a hardcoded /ai/ prefix.
 */
class CommerceEndpointsSource
{
    private const AICOMMERCE_MODULE = 'Secomm_AiCommerce';
    private const AICOMMERCE_ENABLED_PATH = 'seocomm_ai_commerce/general/enabled';
    private const AICOMMERCE_ENDPOINT_PATH = 'seocomm_ai_commerce/general/endpoint_path';
    private const AICOMMERCE_FILTER_ALLOWLIST_PATH = 'seocomm_ai_commerce/general/filter_allowlist';
    private const AICOMMERCE_MAX_PAGE_SIZE_PATH = 'seocomm_ai_commerce/general/max_page_size';
    private const DEFAULT_ENDPOINT_PATH = 'ai';
    private const DEFAULT_FILTER_ALLOWLIST = 'color,size';
    private const DEFAULT_MAX_PAGE_SIZE = 50;
    private const DEFAULT_PAGE_SIZE = 20;
    private const SKU_PLACEHOLDER = '{sku}';

    /**
     * @param ModuleListInterface $moduleList module registry (soft presence check)
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     * @param Config $config module configuration accessor (section titles)
     */
    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config
    ) {
    }

    /**
     * Advertise the bounded read-only commerce endpoints of the store view.
     *
     * Each entry carries a concise factual purpose line (SPEC-TASK-QYZMF1 §3.4)
     * describing what the endpoint actually exposes — no capability beyond the
     * implemented read-only surface is ever stated.
     *
     * @param StoreInterface $store store view scope
     * @return array<int, array{label: string, url: string, purpose: string, plain?: bool}>
     */
    public function getEntries(StoreInterface $store): array
    {
        $storeId = (int) $store->getId();

        if (!$this->isAiCommerceAvailable($storeId)) {
            return [];
        }

        $base = rtrim((string) $store->getBaseUrl(), '/');
        $basePath = $this->getBasePath($storeId);
        $suffix = '?store=' . rawurlencode((string) $store->getCode());

        return [
            [
                'label' => $this->config->getSectionTitle('store_information', $storeId),
                'url' => $base . '/' . $basePath . '/store' . $suffix,
                'purpose' => 'Store metadata: store code, locale, currency and base URL.',
            ],
            [
                'label' => $this->config->getSectionTitle('product_search', $storeId),
                'url' => $base . '/' . $basePath . '/catalog/search' . $suffix,
                'purpose' => 'Search public products using the bounded AI Commerce catalog facade.',
                'usage' => $this->getSearchUsageLines(
                    $storeId,
                    $base . '/' . $basePath . '/catalog/search' . $suffix
                ),
            ],
            [
                'label' => $this->config->getSectionTitle('product_categories', $storeId),
                'url' => $base . '/' . $basePath . '/categories' . $suffix,
                'purpose' => 'Browse public category data for this store view.',
            ],
            // Route template, not a resolvable URL — emitted as plain text.
            [
                'label' => $this->config->getSectionTitle('product_detail', $storeId),
                'url' => $base . '/' . $basePath . '/products/' . self::SKU_PLACEHOLDER . $suffix,
                'purpose' => 'Retrieve public product information for a known SKU.',
                'plain' => true,
            ],
        ];
    }

    /**
     * Deterministic Product Search usage guidance (module-generated text).
     *
     * States ONLY live-verified behavior of the read facade: the parser
     * allowlist (q/category/price bounds/sort allowlist/page bounds/store
     * selector), the configured attribute-filter allowlist and page-size
     * bound (read store-scoped through the soft AiCommerce config seam —
     * never hardcoded), and the 400 error codes for out-of-contract values.
     *
     * @param int $storeId store view scope
     * @param string $url effective Product Search endpoint URL
     * @return string[] plain guidance lines
     */
    private function getSearchUsageLines(int $storeId, string $url): array
    {
        $attributes = $this->getFilterAllowlist($storeId);
        $lines = [
            'Query parameters (all optional, combinable):',
            '- q=KEYWORD: keyword search; omit q to browse the bounded catalog listing.',
            '- category=CATEGORY_ID: one category id from the Categories endpoint; '
            . 'an unknown id returns 400 invalid_parameter.',
            '- price_min=NUMBER / price_max=NUMBER: price bounds in store currency; '
            . 'combined both bounds apply; each works alone.',
            '- sort=relevance|price_asc|price_desc|name_asc|name_desc: result ordering; '
            . 'relevance when omitted.',
            '- price_asc/price_desc order by the catalog price index, which can differ '
            . 'from the displayed price (special/link pricing).',
        ];

        if ($attributes !== []) {
            $lines[] = '- filter[ATTRIBUTE]=OPTION_ID: allowlisted attributes only: '
                . implode(', ', $attributes)
                . '. OPTION_ID is a catalog attribute option id; text values may match nothing.';
        }

        $lines[] = '- page=NUMBER / page_size=NUMBER: 1-based pagination, defaults 1/'
            . self::DEFAULT_PAGE_SIZE
            . ', page_size up to ' . $this->getMaxPageSize($storeId) . '.';
        $lines[] = '- store=STORE_CODE: read a specific store view; omit for the default store view; '
            . 'unknown code returns 400 invalid_store.';
        $lines[] = '- Unknown parameter names (camelCase pageSize included) return 400 invalid_parameter.';
        $lines[] = 'Example: GET ' . $url . '&q=KEYWORD&sort=price_asc';

        $example2 = 'Example: GET ' . $url . '&category=CATEGORY_ID&page=2&page_size=' . self::DEFAULT_PAGE_SIZE;
        if ($attributes !== []) {
            $example2 = 'Example: GET ' . $url . '&category=CATEGORY_ID&filter[ATTRIBUTE]=OPTION_ID'
                . '&price_min=NUMBER&price_max=NUMBER&page=2&page_size=' . self::DEFAULT_PAGE_SIZE;
        }
        $lines[] = $example2;

        return $lines;
    }

    /**
     * Allowed attribute-filter codes, mirroring AiCommerce's normalization
     * (CSV, lowercase/trim, non-empty unique) through the soft config seam.
     *
     * @param int $storeId store view scope
     * @return string[] allowed attribute codes
     */
    private function getFilterAllowlist(int $storeId): array
    {
        $value = $this->scopeConfig->getValue(
            self::AICOMMERCE_FILTER_ALLOWLIST_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // Unset config falls back to the AiCommerce shipped default; an
        // explicitly SAVED empty value means an EMPTY allowlist (the API
        // rejects every attribute filter) — the distinction is the contract.
        $raw = $value === null ? self::DEFAULT_FILTER_ALLOWLIST : (string) $value;

        $codes = array_map(
            static fn (string $code): string => strtolower(trim($code)),
            explode(',', $raw)
        );

        return array_values(array_unique(array_filter($codes, static fn (string $code): bool => $code !== '')));
    }

    /**
     * Effective page_size bound, mirroring AiCommerce's clamping through the
     * soft config seam (non-numeric/low values fall back to the default 50,
     * values above 50 clamp to 50).
     *
     * @param int $storeId store view scope
     * @return int page_size upper bound
     */
    private function getMaxPageSize(int $storeId): int
    {
        $max = (int) $this->scopeConfig->getValue(
            self::AICOMMERCE_MAX_PAGE_SIZE_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($max < 1) {
            $max = self::DEFAULT_MAX_PAGE_SIZE;
        }

        return min($max, self::DEFAULT_MAX_PAGE_SIZE);
    }

    /**
     * Effective AiCommerce base path for the store view.
     *
     * The stored value is canonicalized by AiCommerce's config backend model
     * (leading/trailing slashes stripped, validated); this join-side guard
     * only trims slashes and falls back to the shipped default — it never
     * re-implements validation.
     *
     * @param int $storeId store view scope
     * @return string base path without surrounding slashes
     */
    private function getBasePath(int $storeId): string
    {
        $path = trim((string) $this->scopeConfig->getValue(
            self::AICOMMERCE_ENDPOINT_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ), '/');

        return $path !== '' ? $path : self::DEFAULT_ENDPOINT_PATH;
    }

    /**
     * Whether AiCommerce is present AND enabled for the store view.
     *
     * @param int $storeId store view scope
     * @return bool true when the commerce surface may be advertised
     */
    private function isAiCommerceAvailable(int $storeId): bool
    {
        if (!$this->moduleList->has(self::AICOMMERCE_MODULE)) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::AICOMMERCE_ENABLED_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
