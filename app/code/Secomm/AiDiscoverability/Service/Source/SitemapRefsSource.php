<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Service\Source;

use Magento\Sitemap\Model\ResourceModel\Sitemap\CollectionFactory as SitemapCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

/**
 * References to existing public sitemap files of the store (read-only
 * reference through the core sitemap_sitemap table — no generation, no
 * catalog scan; SPEC-TASK-0X552E §0.C).
 */
class SitemapRefsSource
{
    /**
     * @param SitemapCollectionFactory $collectionFactory sitemap collection factory
     */
    public function __construct(private readonly SitemapCollectionFactory $collectionFactory)
    {
    }

    /**
     * Emit entries referencing the store's existing public sitemap files.
     *
     * @param StoreInterface $store store view scope
     * @return array<int, array{label: string, url: string}>
     */
    public function getEntries(StoreInterface $store): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('store_id', [(int) $store->getId(), 0]);

        $baseUrl = rtrim((string) $store->getBaseUrl(), '/');
        $entries = [];

        foreach ($collection->getItems() as $sitemap) {
            $filename = (string) $sitemap->getSitemapFilename();
            if ($filename === '') {
                continue;
            }

            $path = trim((string) $sitemap->getSitemapPath(), '/');
            $url = $baseUrl . '/' . ($path !== '' ? $path . '/' : '') . $filename;

            $entries[] = ['label' => 'XML Sitemap', 'url' => $url];
        }

        return $entries;
    }
}
