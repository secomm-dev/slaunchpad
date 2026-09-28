<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Model;

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Session storage for pixel events waiting to be tracked client-side on the next
 * page render (e.g. AddToCart stashed right after the server-side S2S publish).
 */
class PendingPixelEventStorage
{
    private const SESSION_KEY = 'secomm_tiktok_hyva_pending_pixel_events';

    private const TTL = 600;

    public function __construct(private readonly CheckoutSession $checkoutSession)
    {
    }

    /**
     * Append a client event payload to the pending list
     *
     * @param array $event
     * @return void
     */
    public function append(array $event): void
    {
        $events = $this->checkoutSession->getData(self::SESSION_KEY);
        if (!is_array($events)) {
            $events = [];
        }
        $events[] = $event;
        $this->checkoutSession->setData(self::SESSION_KEY, $events);
    }

    /**
     * Retrieve pending events and clear the storage (expired entries are dropped)
     *
     * @return array
     */
    public function pull(): array
    {
        $stored = $this->checkoutSession->getData(self::SESSION_KEY);
        $this->checkoutSession->unsetData(self::SESSION_KEY);

        if (!is_array($stored)) {
            return [];
        }

        $now = time();
        $valid = [];
        foreach ($stored as $event) {
            if (!is_array($event)
                || !isset($event['created_at'])
                || !is_numeric($event['created_at'])
                || ($now - (int) $event['created_at']) > self::TTL
            ) {
                continue;
            }
            unset($event['created_at']);
            $valid[] = $event;
        }

        return $valid;
    }
}
