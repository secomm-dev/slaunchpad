<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service\Source;

use Magento\Store\Api\Data\StoreInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\CanonicalPolicy;
use Secomm\AiDiscoverability\Service\EligibilityChecker;
use Secomm\AiDiscoverability\Service\SeoPolicy;

/**
 * Merchant-curated internal priority paths (home page first, then config
 * entries). External/malformed/blocked/noindexed paths are dropped (fail-closed).
 */
class PriorityUrlsSource
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CanonicalPolicy
     */
    private $canonicalPolicy;

    /**
     * @var EligibilityChecker
     */
    private $eligibility;

    /**
     * @var SeoPolicy
     */
    private $seoPolicy;

    /**
     * @param Config $config module configuration accessor
     * @param CanonicalPolicy $canonicalPolicy canonical URL policy
     * @param EligibilityChecker $eligibility route eligibility checker
     * @param SeoPolicy $seoPolicy noindex policy adapter
     */
    public function __construct(
        Config $config,
        CanonicalPolicy $canonicalPolicy,
        EligibilityChecker $eligibility,
        SeoPolicy $seoPolicy
    ) {
        $this->config = $config;
        $this->canonicalPolicy = $canonicalPolicy;
        $this->eligibility = $eligibility;
        $this->seoPolicy = $seoPolicy;
    }

    /**
     * Emit entries for the home page and eligible merchant priority paths.
     *
     * @param StoreInterface $store store view scope
     * @return array<int, array{label: string, url: string}>
     */
    public function getEntries(StoreInterface $store): array
    {
        $entries = [
            ['label' => (string) $store->getName(), 'url' => $this->canonicalPolicy->getHomeUrl($store)],
        ];

        foreach ($this->config->getPriorityPaths((int) $store->getId()) as $path) {
            $path = '/' . ltrim(trim($path), '/');

            if (!$this->eligibility->isEligiblePath($path)) {
                continue;
            }
            if ($this->seoPolicy->isNoindexed($path, (int) $store->getId())) {
                continue;
            }

            $entries[] = [
                'label' => $this->labelFromPath($path),
                'url' => $this->canonicalPolicy->getUrlForPath($path, $store),
            ];
        }

        return $entries;
    }

    /**
     * Derive a human-readable label from an internal path.
     *
     * @param string $path internal path
     * @return string display label
     */
    private function labelFromPath(string $path): string
    {
        $basename = trim($path, '/');

        return ucwords(str_replace(['-', '_'], ' ', $basename ?: 'Home'));
    }
}
