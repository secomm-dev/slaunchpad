<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service\Source;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\CanonicalPolicy;
use Secomm\AiDiscoverability\Service\SeoPolicy;

/**
 * Selected CMS pages: only active pages visible in the current store,
 * identifier used as the self-canonical path (SPEC-TASK-0X552E §3.1).
 */
class CmsPagesSource
{
    public const MAX_ENTRIES = 20;

    /**
     * @param Config $config module configuration accessor
     * @param PageRepositoryInterface $pageRepository CMS page repository
     * @param CanonicalPolicy $canonicalPolicy canonical URL policy
     * @param SeoPolicy $seoPolicy noindex policy adapter
     * @param LoggerInterface $logger PSR logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly CanonicalPolicy $canonicalPolicy,
        private readonly SeoPolicy $seoPolicy,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Emit canonical entries for the store's selected CMS pages.
     *
     * @param StoreInterface $store store view scope
     * @return array<int, array{label: string, url: string}>
     */
    public function getEntries(StoreInterface $store): array
    {
        $storeId = (int) $store->getId();
        $entries = [];

        foreach ($this->config->getCmsPageIds($storeId) as $pageId) {
            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }

            try {
                $page = $this->pageRepository->getById($pageId);
            } catch (LocalizedException $e) {
                continue;
            }

            if (!$this->isVisibleInStore($page, $storeId)) {
                continue;
            }

            $identifier = (string) $page->getIdentifier();

            if ($identifier === '') {
                continue;
            }
            if ($this->seoPolicy->isNoindexed('/' . $identifier, $storeId)) {
                continue;
            }

            $entries[] = [
                'label' => (string) ($page->getTitle() !== '' ? $page->getTitle() : $identifier),
                'url' => $this->canonicalPolicy->getUrlForPath($identifier, $store),
            ];
        }

        return $entries;
    }

    /**
     * Whether a CMS page is active and visible in a store view.
     *
     * @param PageInterface $page candidate CMS page
     * @param int $storeId store view id
     * @return bool true when visible in the store
     */
    private function isVisibleInStore(PageInterface $page, int $storeId): bool
    {
        if (!(bool) $page->isActive()) {
            return false;
        }

        $stores = array_map('intval', (array) $page->getStores());

        return in_array(0, $stores, true) || in_array($storeId, $stores, true);
    }
}
