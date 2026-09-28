<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Sales\Model\Order;
use Throwable;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Event\TiktokEventFactory;

/**
 * Server-side pixel events outside the vendor pool: publish S2S, then stash the
 * payload (same event_id) so the next page render tracks it client-side — TikTok
 * dedupes the pair. Used by observers and the AccountManagement plugin.
 */
class PendingEventTracker
{
    /**
     * Per-request guard: some flows invoke several create* service methods in one
     * request (SocialLogin popup) — track each event name at most once.
     *
     * @var array<string, true>
     */
    private array $trackedInRequest = [];

    public function __construct(
        private readonly TiktokEventFactory $eventFactory,
        private readonly PendingPixelEventStorage $pendingPixelEventStorage,
        private readonly State $appState,
        private readonly TiktokLogger $logger
    ) {
    }

    /**
     * Build, publish and stash one event
     *
     * @param string $eventName TikTok event name (e.g. PlaceAnOrder)
     * @param Product|null $product
     * @param Order|null $order
     * @return void
     */
    public function track(string $eventName, ?Product $product = null, ?Order $order = null): void
    {
        try {
            if (isset($this->trackedInRequest[$eventName])) {
                $this->logger->info("Secomm_TiktokHyva: {$eventName} skipped (already tracked in this request)");
                return;
            }
            $this->trackedInRequest[$eventName] = true;
            try {
                $area = $this->appState->getAreaCode();
            } catch (Throwable) {
                $area = 'unset';
            }
            if ($area === Area::AREA_ADMINHTML) {
                $this->logger->info("Secomm_TiktokHyva: {$eventName} skipped (admin area)");
                return;
            }
            $event = $this->eventFactory->create();
            if (!$event->shouldReport() || !$event->isPixelTrackingEnabled()) {
                $this->logger->info(sprintf(
                    'Secomm_TiktokHyva: %1$s skipped (shouldReport=%2$s, pixelTracking=%3$s, area=%4$s)',
                    $eventName,
                    $event->shouldReport() ? 'true' : 'false',
                    $event->isPixelTrackingEnabled() ? 'true' : 'false',
                    $area
                ));
                return;
            }

            $event->setEventName($eventName);
            if ($product !== null && $product->getId()) {
                $event->addProduct($product);
            }
            if ($order !== null) {
                $event->addOrderToEvent();
            }
            $event->publish();

            $data = $event->getEvent()['data'] ?? [];
            if (!is_string($data['event_id'] ?? null)) {
                $this->logger->info("Secomm_TiktokHyva: {$eventName} skipped (no event_id in payload)");
                return;
            }
            $this->pendingPixelEventStorage->append([
                'event' => (string) ($data['event'] ?? $eventName),
                'event_id' => $data['event_id'],
                'properties' => is_array($data['properties'] ?? null) ? $data['properties'] : [],
                'created_at' => time(),
            ]);
        } catch (Throwable $e) {
            // Never break wishlist/registration/checkout flows. NOTE: logged at INFO —
            // the vendor TiktokHandler::isHandling uses exact-level match, so ERROR
            // records are silently dropped and would never reach tiktok.log.
            $this->logger->info(
                sprintf('Secomm_TiktokHyva: failed to stash pending %s event: %s', $eventName, $e->getMessage())
            );
        }
    }
}
