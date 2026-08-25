<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\Source\CategoriesSource;
use Secomm\AiDiscoverability\Service\Source\CmsPagesSource;
use Secomm\AiDiscoverability\Service\Source\CommerceEndpointsSource;
use Secomm\AiDiscoverability\Service\Source\PriorityUrlsSource;
use Secomm\AiDiscoverability\Service\Source\SitemapRefsSource;

/**
 * Single generation path for BOTH the public endpoint and the admin preview
 * (SPEC-TASK-0X552E §4.2): same store + same effective config + same source
 * state => same generated body bytes.
 *
 * Section order is fixed: Priority Pages, Collections, Pages, Sitemap.
 */
class LlmsTxtGenerator
{
    private const SECTION_PRIORITY = 'Priority Pages';
    private const SECTION_COLLECTIONS = 'Collections';
    private const SECTION_PAGES = 'Pages';
    private const SECTION_SITEMAP = 'Sitemap';
    private const SECTION_COMMERCE = 'Machine-readable Commerce';

    /**
     * @param Config $config module configuration accessor
     * @param StoreManagerInterface $storeManager store registry
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     * @param PriorityUrlsSource $priorityUrlsSource priority pages source
     * @param CmsPagesSource $cmsPagesSource CMS pages source
     * @param CategoriesSource $categoriesSource categories source
     * @param SitemapRefsSource $sitemapRefsSource sitemap references source
     * @param CommerceEndpointsSource $commerceEndpointsSource commerce discovery source (soft seam)
     * @param UrlCollector $collector URL dedup/bound collector
     * @param LlmsTxtFormatter $formatter llms.txt formatter
     * @param LoggerInterface $logger PSR logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PriorityUrlsSource $priorityUrlsSource,
        private readonly CmsPagesSource $cmsPagesSource,
        private readonly CategoriesSource $categoriesSource,
        private readonly SitemapRefsSource $sitemapRefsSource,
        private readonly CommerceEndpointsSource $commerceEndpointsSource,
        private readonly UrlCollector $collector,
        private readonly LlmsTxtFormatter $formatter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate the deterministic llms.txt body for a store view.
     *
     * @param int $storeId store view id
     * @return string llms.txt body
     */
    public function generate(int $storeId): string
    {
        /** @var StoreInterface $store */
        $store = $this->storeManager->getStore($storeId);
        $maxUrls = $this->config->getMaxUrls($storeId);

        $sections = [
            self::SECTION_PRIORITY => $this->priorityUrlsSource->getEntries($store),
            self::SECTION_COLLECTIONS => $this->collector->sortByLabel($this->categoriesSource->getEntries($store)),
            self::SECTION_PAGES => $this->collector->sortByLabel($this->cmsPagesSource->getEntries($store)),
            self::SECTION_SITEMAP => $this->config->isIncludeSitemapRefs($storeId)
                ? $this->sitemapRefsSource->getEntries($store)
                : [],
            // Soft seam: empty when Secomm_AiCommerce is absent or disabled
            // (SPEC-TASK-7FBHHC §2.1) — no endpoint execution, no catalog load.
            self::SECTION_COMMERCE => $this->commerceEndpointsSource->getEntries($store),
        ];

        // Cross-section dedup + global bound, section order preserved.
        $seen = [];
        $total = 0;
        $bounded = [];

        foreach ($sections as $heading => $entries) {
            foreach ($entries as $entry) {
                if ($total >= $maxUrls) {
                    break 2;
                }
                if (isset($seen[$entry['url']])) {
                    continue;
                }
                $seen[$entry['url']] = true;
                $bounded[$heading][] = $entry;
                $total++;
            }
        }

        $candidateCount = array_sum(array_map('count', $sections));
        if ($total < $candidateCount) {
            $this->logger->notice('LC-30: llms.txt output bounded to {emitted} of {total} candidate URLs', [
                'emitted' => $total,
                'total' => $candidateCount,
                'store' => $storeId,
            ]);
        }

        // Summary fallback: configured brand_summary, else the effective public
        // site title. The internal store-view name is never emitted as the
        // AI-facing summary (SPEC-TASK-0X552E §12.3); when no safe public text
        // exists the blockquote is omitted entirely.
        $summary = $this->config->getBrandSummary($storeId);
        if ($summary === '') {
            $summary = $this->config->getSiteTitle($storeId);
        }

        $locale = (string) $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $title = $this->config->getSiteTitle($storeId);
        if ($title === '') {
            // Last resort only: internal store-view label (may be non-public).
            $title = (string) $store->getName();
        }

        $currency = $this->config->getCurrencyCode($storeId);

        return $this->formatter->format($title, $summary, $locale, $bounded, $currency);
    }
}
