<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Secomm\TiktokHyva\Model\PendingPixelEventStorage;
use Throwable;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Tiktok\Tiktok\Observer\AddToCartObserver;

/**
 * The vendor observer publishes AddToCart server-side (S2S queue) and, on Luma,
 * hands the payload to the AJAX response. On Hyvä neither channel reaches the
 * pixel directly, so stash the payload (same event_id as the S2S event) for the
 * next page render — TikTok dedupes pixel + S2S by event_id.
 */
class AddToCartPendingEvent
{
    public function __construct(
        private readonly TiktokEvent $tiktokEvent,
        private readonly PendingPixelEventStorage $pendingPixelEventStorage,
        private readonly State $appState,
        private readonly TiktokLogger $logger
    ) {
    }

    /**
     * Stash the pixel payload after the vendor observer published the S2S event
     *
     * @param AddToCartObserver $subject
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(AddToCartObserver $subject): void
    {
        try {
            if ($this->appState->getAreaCode() === Area::AREA_ADMINHTML) {
                return;
            }
            if (!$this->tiktokEvent->shouldReport() || !$this->tiktokEvent->isPixelTrackingEnabled()) {
                return;
            }

            $data = $this->tiktokEvent->getEvent()['data'] ?? [];
            if (($data['event'] ?? null) !== 'AddToCart' || !is_string($data['event_id'] ?? null)) {
                return;
            }

            $this->pendingPixelEventStorage->append([
                'event' => $data['event'],
                'event_id' => $data['event_id'],
                'properties' => is_array($data['properties'] ?? null) ? $data['properties'] : [],
                'created_at' => time(),
            ]);
        } catch (Throwable $e) {
            // Never break add-to-cart. INFO level — vendor handler drops ERROR records
            // (exact-level isHandling), so error() would be invisible.
            $this->logger->info(
                'Secomm_TiktokHyva: failed to stash pending AddToCart pixel event: ' . $e->getMessage()
            );
        }
    }
}
