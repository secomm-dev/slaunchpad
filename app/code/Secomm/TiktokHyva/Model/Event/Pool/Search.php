<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Model\Event\Pool;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Event\Pool\MetadataInterface;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Tiktok\Tiktok\Model\Event\TiktokEventFactory;

/**
 * Search — enriched with the top result products so contents/content_id/value ride
 * along (TikTok flags Search events missing value/content_id). search_string cannot
 * ride the vendor S2S payload (no generic property setter) — the client-side track
 * injects it and TikTok merges both channels by event_id.
 */
class Search implements MetadataInterface
{
    private const MAX_RESULT_PRODUCTS = 10;

    public function __construct(
        private readonly TiktokEventFactory $eventFactory,
        private readonly CollectionFactory $fulltextCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState,
        private readonly TiktokLogger $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(?array $context = null): array
    {
        $event = $this->eventFactory->create();
        $event->setEventName('Search');

        $term = is_string($context['search_string'] ?? null) ? trim($context['search_string']) : '';
        if ($term !== '' && $this->isFrontend()) {
            foreach ($this->getResultProducts($term) as $product) {
                $event->addProduct($product);
            }
        }
        $event->publish();

        return $event->getEvent();
    }

    /**
     * Top search-result products (contents + value source)
     *
     * @param string $term
     * @return array
     */
    private function getResultProducts(string $term): array
    {
        try {
            /** @var FulltextCollection $collection */
            $collection = $this->fulltextCollectionFactory->create();
            $collection->setStoreId($this->storeManager->getStore()->getId());
            $collection->addSearchFilter($term);
            $collection->setPageSize(self::MAX_RESULT_PRODUCTS)->setCurPage(1);
            // price/name are not selected by default — without them getFinalPrice()
            // returns 0 (=> no "value") and content_name is null.
            $collection->addAttributeToSelect(['name', 'price', 'special_price', 'special_from_date', 'special_to_date']);
            $collection->load();

            return iterator_to_array($collection->getItems());
        } catch (Throwable $e) {
            // A failed lookup must never break the search page. INFO level — the vendor
            // TiktokHandler::isHandling drops ERROR records (exact-level match).
            $this->logger->info('Secomm_TiktokHyva: search result lookup failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Search pages are storefront-only; skip the lookup elsewhere
     *
     * @return bool
     */
    private function isFrontend(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (Throwable) {
            return false;
        }
    }
}
