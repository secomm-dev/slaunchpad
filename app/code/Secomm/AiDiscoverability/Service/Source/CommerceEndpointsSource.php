<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Machine-readable Commerce discovery entries for the Secomm_AiCommerce
 * read-only surface (SPEC-TASK-7FBHHC §2.1).
 *
 * Discovery metadata ONLY: no AiCommerce endpoint is executed and no catalog
 * data is loaded while generating these entries — the section is derived from
 * module presence + one config flag. The seam is deliberately soft: no
 * AiCommerce class is referenced (ModuleList + config path), so DI compilation
 * and generation keep working when Secomm_AiCommerce is absent or disabled,
 * in which case this source yields no entries.
 */
class CommerceEndpointsSource
{
    private const AICOMMERCE_MODULE = 'Secomm_AiCommerce';
    private const AICOMMERCE_ENABLED_PATH = 'seocomm_ai_commerce/general/enabled';
    private const SKU_PLACEHOLDER = '{sku}';

    /**
     * @param ModuleListInterface $moduleList module registry (soft presence check)
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     */
    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly ScopeConfigInterface $scopeConfig
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
        if (!$this->isAiCommerceAvailable((int) $store->getId())) {
            return [];
        }

        $base = rtrim((string) $store->getBaseUrl(), '/');
        $suffix = '?store=' . rawurlencode((string) $store->getCode());

        return [
            [
                'label' => 'Store Information',
                'url' => $base . '/ai/store' . $suffix,
                'purpose' => 'Store metadata, locale, currency and supported public catalog context.',
            ],
            [
                'label' => 'Product Search',
                'url' => $base . '/ai/catalog/search' . $suffix,
                'purpose' => 'Search public products using the bounded AI Commerce catalog facade.',
            ],
            [
                'label' => 'Categories',
                'url' => $base . '/ai/categories' . $suffix,
                'purpose' => 'Browse public category data for this store view.',
            ],
            // Route template, not a resolvable URL — emitted as plain text.
            [
                'label' => 'Product Detail',
                'url' => $base . '/ai/products/' . self::SKU_PLACEHOLDER . $suffix,
                'purpose' => 'Retrieve public product information for a known SKU.',
                'plain' => true,
            ],
        ];
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
