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
 * Section order is fixed (SPEC-TASK-QYZMF1 §3.1): Store Summary, Agent
 * Guidance, Priority Pages, Featured Collections, Key Pages,
 * Machine-readable Commerce, Commerce Limitations, Sitemap.
 */
class LlmsTxtGenerator
{
    private const SECTION_SITEMAP = 'Sitemap';

    /**
     * Deterministic agent guidance describing the ACTUAL module capability:
     * a read-only machine-readable catalog surface — nothing transactional,
     * no numeric rate limits (edge configuration may differ).
     */
    private const AGENT_GUIDANCE_LINES = [
        'This site provides public machine-readable commerce endpoints for catalog discovery.',
        'Use the machine-readable endpoints below when structured catalog data is '
        . 'sufficient instead of scraping storefront HTML.',
        'The current machine-readable interface is read-only.',
        'Do not use these endpoints for:',
        '- cart creation',
        '- checkout',
        '- customer accounts',
        '- orders',
        '- addresses',
        '- payments',
        'Respect HTTP rate limits and cache responses where appropriate.',
    ];

    /**
     * Deterministic statement of what the commerce interface verifiably does
     * NOT expose (it is read-only catalog data only).
     */
    private const COMMERCE_LIMITATION_LINES = [
        'The current interface does NOT expose:',
        '- cart creation',
        '- checkout',
        '- payment',
        '- customer account data',
        '- order data',
        '- address data',
        'Transactional operations must use the storefront.',
    ];

    /**
     * @param Config $config module configuration accessor
     * @param StoreManagerInterface $storeManager store registry
     * @param ScopeConfigInterface $scopeConfig scoped config reader
     * @param PriorityUrlsSource $priorityUrlsSource priority pages source
     * @param CmsPagesSource $cmsPagesSource CMS pages source
     * @param CategoriesSource $categoriesSource categories source
     * @param SitemapRefsSource $sitemapRefsSource sitemap references source
     * @param CommerceEndpointsSource $commerce commerce discovery source (soft seam)
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

        // Configurable section titles (store-view scoped, defaults preserve
        // the historical output — SPEC-TASK-0X552E §4.3 titles group).
        $titlePriority = $this->config->getSectionTitle('priority_pages', $storeId);
        $titleCollections = $this->config->getSectionTitle('featured_collections', $storeId);
        $titlePages = $this->config->getSectionTitle('key_pages', $storeId);
        $titleCommerce = $this->config->getSectionTitle('machine_commerce', $storeId);

        $sections = [
            $titlePriority => $this->priorityUrlsSource->getEntries($store),
            $titleCollections => $this->collector->sortByLabel($this->categoriesSource->getEntries($store)),
            $titlePages => $this->collector->sortByLabel($this->cmsPagesSource->getEntries($store)),
            // Soft seam: empty when Secomm_AiCommerce is absent or disabled
            // (SPEC-TASK-7FBHHC §2.1) — no endpoint execution, no catalog load.
            $titleCommerce => array_map(
                static fn (array $entry): array => ['detail' => $entry],
                $this->commerceEndpointsSource->getEntries($store)
            ),
            self::SECTION_SITEMAP => $this->config->isIncludeSitemapRefs($storeId)
                ? $this->sitemapRefsSource->getEntries($store)
                : [],
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
                // Commerce entries carry their URL inside the detail payload.
                $url = (string) ($entry['detail']['url'] ?? $entry['url'] ?? '');
                if ($url === '' || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
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
        $brandSummary = $this->config->getBrandSummary($storeId);
        $summary = $brandSummary !== '' ? $brandSummary : $this->config->getSiteTitle($storeId);

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

        // Prose sections (SPEC-TASK-QYZMF1 §3.2) — carry no URLs of their own
        // and are excluded from the global URL bound. Store Summary renders
        // only from configured brand summary text (never generated). The
        // commerce-dependent sections render ONLY when the machine-readable
        // surface is actually available, so a store without it never implies
        // /ai/* (or the configured base path) exists.
        $commerceAvailable = !empty($bounded[$titleCommerce]);

        $ordered = [];

        if ($brandSummary !== '') {
            $ordered[$this->config->getSectionTitle('store_summary', $storeId)] = [['prose' => [$brandSummary]]];
        }
        if ($commerceAvailable) {
            $ordered[$this->config->getSectionTitle('agent_guidance', $storeId)] = [
                ['prose' => self::AGENT_GUIDANCE_LINES],
            ];
        }
        $ordered[$titlePriority] = $bounded[$titlePriority] ?? [];
        $ordered[$titleCollections] = $bounded[$titleCollections] ?? [];
        $ordered[$titlePages] = $bounded[$titlePages] ?? [];
        $ordered[$titleCommerce] = $bounded[$titleCommerce] ?? [];
        if ($commerceAvailable) {
            $ordered[$this->config->getSectionTitle('commerce_limitations', $storeId)] = [
                ['prose' => self::COMMERCE_LIMITATION_LINES],
            ];
        }
        $ordered[self::SECTION_SITEMAP] = $bounded[self::SECTION_SITEMAP] ?? [];

        return $this->formatter->format($title, $summary, $locale, $ordered, $currency);
    }
}
