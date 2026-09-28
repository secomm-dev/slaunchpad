<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Secomm\TiktokHyva\Model\PendingPixelEventStorage;
use Tiktok\Tiktok\Model\Event\Pool;
use Tiktok\Tiktok\Model\Event\TiktokEvent;

/**
 * Hyvä pixel view model — reuses the vendor event pool (which also publishes the
 * server-side S2S event) and shapes the payloads for the inline vanilla JS tracker.
 */
class Pixel implements ArgumentInterface
{
    private const MAX_SEARCH_STRING_LENGTH = 128;

    public function __construct(
        private readonly TiktokEvent $tiktokEvent,
        private readonly Pool $eventPool,
        private readonly PendingPixelEventStorage $pendingPixelEventStorage,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CatalogHelper $catalogHelper,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Whether the pixel base script should be rendered for the current scope
     *
     * @return bool
     */
    public function isPixelEnabled(): bool
    {
        return $this->tiktokEvent->shouldReport() && $this->tiktokEvent->isPixelTrackingEnabled();
    }

    /**
     * TikTok pixel code configured for the current website
     *
     * @return string|null
     */
    public function getPixelCode(): ?string
    {
        return $this->tiktokEvent->getScopeManager()->getPixelCode();
    }

    /**
     * Current catalog product id (for the endpoint to rebuild product context in
     * its own request — the catalog registry is not shared across requests)
     *
     * @return int|null
     */
    public function getProductId(): ?int
    {
        $product = $this->catalogHelper->getProduct();

        return $product?->getId() ? (int) $product->getId() : null;
    }

    /**
     * Current search term (search results page render request)
     *
     * @return string|null
     */
    public function getSearchTerm(): ?string
    {
        $term = $this->request->getParam('q');
        if (!is_string($term)) {
            return null;
        }
        $term = trim($term);

        return $term === '' ? null : mb_substr($term, 0, self::MAX_SEARCH_STRING_LENGTH);
    }

    /**
     * Build the full client payload in one shot — the vendor pool must execute at
     * most once per event type per request (each execution publishes an S2S event).
     *
     * @param array $eventTypes vendor pool keys (PageView, ViewContent, ...)
     * @param int|null $productId current PDP product, resolved client-side page
     * @param string $searchString current search query (Search is pixel-only)
     * @return array {user: ?array, events: array}
     */
    public function getPayload(array $eventTypes, ?int $productId = null, string $searchString = ''): array
    {
        $events = [];
        $user = null;
        $product = $this->resolveProduct($productId);
        $context = $product ? ['product' => $product] : [];
        if ($searchString !== '') {
            $context['search_string'] = $searchString;
        }

        foreach ($eventTypes as $eventType) {
            // Never emit a content-less ViewContent — TikTok flags events missing content_id.
            if ($eventType === 'ViewContent' && (!$product instanceof ProductInterface || !$product->getId())) {
                continue;
            }
            $event = $this->eventPool->execute((string) $eventType, $context ?: null);
            if ($event === null) {
                continue;
            }
            $data = $event['data'] ?? [];
            $user = $user ?? (is_array($data['user'] ?? null) ? $data['user'] : null);

            if (is_string($data['event'] ?? null) && is_string($data['event_id'] ?? null)) {
                $properties = is_array($data['properties'] ?? null) ? $data['properties'] : [];
                if ($eventType === 'Search' && $searchString !== '') {
                    $properties['search_string'] = $searchString;
                }
                $events[] = [
                    'event' => $data['event'],
                    'event_id' => $data['event_id'],
                    'properties' => $properties,
                ];
            }
        }

        foreach ($this->pendingPixelEventStorage->pull() as $pendingEvent) {
            $events[] = $pendingEvent;
        }

        return [
            'user' => $user,
            'events' => $this->dedupeByEventId($events),
        ];
    }

    /**
     * Resolve the product for pool context: endpoint-supplied id first, then the
     * current-page catalog product (covers non-AJAX renders)
     *
     * @param int|null $productId
     * @return ProductInterface|null
     */
    private function resolveProduct(?int $productId): ?ProductInterface
    {
        if (!$productId) {
            $current = $this->catalogHelper->getProduct();

            return $current?->getId() ? $current : null;
        }

        try {
            return $this->productRepository->getById($productId);
        } catch (NoSuchEntityException) {
            // Invalid client-supplied id — fall through to a null product context.
            return null;
        }
    }

    /**
     * Guard against rendering the same event_id twice on one page
     *
     * @param array $events
     * @return array
     */
    private function dedupeByEventId(array $events): array
    {
        $seen = [];
        $unique = [];
        foreach ($events as $event) {
            $eventId = is_string($event['event_id'] ?? null) ? $event['event_id'] : null;
            if ($eventId !== null) {
                if (isset($seen[$eventId])) {
                    continue;
                }
                $seen[$eventId] = true;
            }
            $unique[] = $event;
        }

        return $unique;
    }
}
