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
    private const DEFAULT_ENDPOINT_PATH = 'ai';
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
                'purpose' => 'Store metadata, locale, currency and supported public catalog context.',
            ],
            [
                'label' => $this->config->getSectionTitle('product_search', $storeId),
                'url' => $base . '/' . $basePath . '/catalog/search' . $suffix,
                'purpose' => 'Search public products using the bounded AI Commerce catalog facade.',
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
