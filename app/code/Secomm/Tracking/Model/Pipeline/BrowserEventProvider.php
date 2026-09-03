<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Pipeline;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Sales\Model\Order;
use Secomm\Tracking\Model\Config;
use Secomm\Tracking\Model\Event\EventNormalizer;

/**
 * FEAT-31X6N2 / TASK-E0NG8Z — resolves the browser-context commerce event for the
 * current page (PDP, category, search, cart, OSC, success) into the launchpad_event
 * dataLayer object. Deterministic event ids (spec §7) so browser and server dedup.
 *
 * Cookie params (fbp/fbc/ttclid/ttp) are read client-side at push time by the
 * template — they never round-trip through the server (spec §5.4).
 */
class BrowserEventProvider
{
    /**
     * Full-action → browser event map. Checkout/cart pages rely on Magefan's own
     * dataLayer for those GA4 events; we only add launchpad_event where a
     * deterministic id adds value (dedup with server or FPC-stable id).
     */
    private const ACTION_MAP = [
        'catalog_product_view' => 'view_item',
        'catalog_category_view' => 'view_category',
        'catalogsearch_result_index' => 'search',
        'checkout_cart_index' => 'view_cart',
        'onestepcheckout_index_index' => 'begin_checkout',
        'checkout_index_index' => 'begin_checkout',
        'hyva_checkout_index_index' => 'begin_checkout',
        'checkout_onepage_success' => 'purchase',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly EventNormalizer $normalizer,
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly CheckoutSession $checkoutSession,
        private readonly SearchHelper $searchHelper
    ) {
    }

    /**
     * @param string|null $scopeCode
     * @return array<string, mixed>|null launchpad_event payload; null when the page
     * has no mapped commerce event or tracking is off.
     */
    public function getEvent(?string $scopeCode = null): ?array
    {
        if (!$this->config->isEnabled($scopeCode)) {
            return null;
        }

        $action = (string)$this->request->getFullActionName();
        $eventName = self::ACTION_MAP[$action] ?? null;
        if ($eventName === null) {
            return null;
        }

        return match ($eventName) {
            'view_item' => $this->viewItemEvent(),
            'view_category' => $this->viewCategoryEvent(),
            'search' => $this->searchEvent(),
            'view_cart' => $this->cartEvent('view_cart'),
            'begin_checkout' => $this->cartEvent('begin_checkout'),
            'purchase' => $this->purchaseEvent(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viewItemEvent(): ?array
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product || !$product->getId()) {
            return null;
        }

        return $this->baseEvent(
            'view_item',
            $this->normalizer->browserEventId('view_item', (int)$product->getId()),
            (float)$product->getFinalPrice(),
            [[
                'item_id' => (string)$product->getSku(),
                'item_name' => (string)$product->getName(),
                'price' => (float)$product->getFinalPrice(),
                'quantity' => 1,
            ]]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viewCategoryEvent(): ?array
    {
        $category = $this->registry->registry('current_category');
        if (!$category instanceof Category || !$category->getId()) {
            return null;
        }

        return $this->baseEvent(
            'view_category',
            $this->normalizer->browserEventId('view_category', (int)$category->getId()),
            0.0,
            []
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function searchEvent(): ?array
    {
        $term = trim((string)$this->searchHelper->getEscapedQueryText());
        if ($term === '') {
            return null;
        }

        return $this->baseEvent(
            'search',
            $this->normalizer->browserEventId('search', substr($term, 0, 32)),
            0.0,
            [],
            ['search_term' => $term]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cartEvent(string $eventName): ?array
    {
        $quote = $this->currentQuote();
        if ($quote === null || !$quote->getItemsQty()) {
            return null;
        }

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => (string)$item->getSku(),
                'item_name' => (string)$item->getName(),
                'price' => (float)$item->getPrice(),
                'quantity' => (float)$item->getQty(),
            ];
        }

        return $this->baseEvent(
            $eventName,
            $this->normalizer->browserEventId($eventName, (int)$quote->getId()),
            (float)$quote->getGrandTotal(),
            $items
        );
    }

    /**
     * Success page — the one event whose id MUST equal the server's CAPI event id
     * (purchase-{increment_id}, spec §7 / DEC D3).
     *
     * @return array<string, mixed>|null
     */
    private function purchaseEvent(): ?array
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
        } catch (LocalizedException|NoSuchEntityException) {
            return null;
        }
        if (!$order instanceof Order || !$order->getId()) {
            return null;
        }

        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => (string)$item->getSku(),
                'item_name' => (string)$item->getName(),
                'price' => (float)$item->getPrice(),
                'quantity' => (float)$item->getQtyOrdered(),
            ];
        }

        return $this->baseEvent(
            'purchase',
            $this->normalizer->purchaseEventId((string)$order->getIncrementId()),
            (float)$order->getGrandTotal(),
            $items,
            ['order_id' => (string)$order->getIncrementId()]
        );
    }

    /**
     * @param string $event
     * @param string $eventId
     * @param float $value
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function baseEvent(string $event, string $eventId, float $value, array $items, array $extra = []): array
    {
        return array_merge($extra, [
            'event' => $event,
            'event_id' => $eventId,
            'value' => $value,
            'currency' => $this->currencyCode(),
            'items' => $items,
        ]);
    }

    /**
     * Display currency of the current store.
     *
     * @return string
     */
    private function currencyCode(): string
    {
        try {
            return (string)$this->checkoutSession->getQuote()->getCurrency()->getQuoteCurrencyCode();
        } catch (LocalizedException|NoSuchEntityException) {
            return 'VND';
        }
    }

    /**
     * @return CartInterface|\Magento\Quote\Model\Quote|null
     */
    private function currentQuote(): ?CartInterface
    {
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (LocalizedException|NoSuchEntityException) {
            return null;
        }

        return $quote instanceof CartInterface ? $quote : null;
    }
}
