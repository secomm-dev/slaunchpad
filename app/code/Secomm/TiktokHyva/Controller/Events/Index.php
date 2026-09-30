<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Controller\Events;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Secomm\TiktokHyva\ViewModel\Pixel;

/**
 * Per-request pixel event payload for the Hyvä inline pixel (session-safe, POST only).
 * Mirrors the vendor endpoint tiktok/tiktokevents but also returns pending events
 * (AddToCart/AddToWishlist/CompleteRegistration/PlaceAnOrder stashed server-side)
 * and the identify user object.
 */
class Index implements HttpPostActionInterface
{
    /**
     * Whitelist of vendor + Secomm event-pool keys this endpoint may build.
     * Must stay in sync with the merged di.xml event pool.
     */
    private const ALLOWED_EVENT_TYPES = [
        'PageView',
        'ViewContent',
        'InitiateCheckout',
        'CompletePayment',
        'Search',
        'AddPaymentInfo',
    ];

    private const MAX_SEARCH_STRING_LENGTH = 128;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Pixel $pixelViewModel
    ) {
    }

    /**
     * Validate + delegate event building to the view model
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->pixelViewModel->isPixelEnabled()) {
            return $this->jsonFactory->create()->setData(['user' => null, 'events' => []]);
        }

        $body = json_decode($this->request->getContent() ?: '', true) ?? [];
        $requestedTypes = is_array($body['event_types'] ?? null) ? $body['event_types'] : [];
        $eventTypes = array_values(
            array_intersect(array_filter($requestedTypes, 'is_string'), self::ALLOWED_EVENT_TYPES)
        );
        $productId = is_numeric($body['product'] ?? null) ? (int) $body['product'] : null;
        $searchString = '';
        if (is_string($body['search_string'] ?? null)) {
            $searchString = mb_substr(trim($body['search_string']), 0, self::MAX_SEARCH_STRING_LENGTH);
        }

        return $this->jsonFactory->create()->setData(
            $this->pixelViewModel->getPayload($eventTypes, $productId, $searchString)
        );
    }
}
