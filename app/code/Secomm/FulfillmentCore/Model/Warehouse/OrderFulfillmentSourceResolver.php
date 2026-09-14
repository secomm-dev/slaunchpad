<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Warehouse;

use Magento\Framework\DataObject;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventorySalesApi\Model\GetAssignedStockIdForWebsiteInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Secomm\FulfillmentCore\Api\OrderFulfillmentSourceResolverInterface;
use Throwable;

/**
 * Resolve MSI source codes for an order: item source_code → website stock → default source.
 */
class OrderFulfillmentSourceResolver implements OrderFulfillmentSourceResolverInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly GetAssignedStockIdForWebsiteInterface $getAssignedStockIdForWebsite,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesForStock,
        private readonly DefaultSourceProviderInterface $defaultSourceProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param OrderInterface $order Magento order
     * @return string[] Unique source codes; first entry is primary
     */
    public function resolveSourceCodes(OrderInterface $order): array
    {
        $fromItems = $this->collectItemSourceCodes($order);
        if ($fromItems !== []) {
            if (count($fromItems) > 1) {
                $this->logger->warning(
                    'FulfillmentCore multi-source order; using first source as primary',
                    [
                        'order_id' => (int) $order->getEntityId(),
                        'sources' => $fromItems,
                    ]
                );
            }
            return $fromItems;
        }

        $fromStock = $this->collectWebsiteStockSources($order);
        if ($fromStock !== []) {
            if (count($fromStock) > 1) {
                $this->logger->warning(
                    'FulfillmentCore multi-source stock; using first source as primary',
                    [
                        'order_id' => (int) $order->getEntityId(),
                        'sources' => $fromStock,
                    ]
                );
            }
            return $fromStock;
        }

        return [$this->defaultSourceProvider->getCode()];
    }

    /**
     * Collect unique MSI source codes from order line items.
     *
     * @param OrderInterface $order Magento sales order
     * @return string[] Source codes in first-seen order
     */
    private function collectItemSourceCodes(OrderInterface $order): array
    {
        $codes = [];
        foreach ($order->getItems() ?? [] as $item) {
            if (!is_object($item)) {
                continue;
            }
            $parentItemId = method_exists($item, 'getParentItemId') ? $item->getParentItemId() : null;
            if ($parentItemId) {
                continue;
            }

            $sourceCode = null;
            if ($item instanceof DataObject) {
                $raw = $item->getData('source_code');
                $sourceCode = is_string($raw) ? $raw : null;
            } elseif (method_exists($item, 'getData')) {
                $raw = $item->getData('source_code');
                $sourceCode = is_string($raw) ? $raw : null;
            }

            if (is_string($sourceCode) && $sourceCode !== '' && !in_array($sourceCode, $codes, true)) {
                $codes[] = $sourceCode;
            }
        }

        return $codes;
    }

    /**
     * @return string[]
     */
    private function collectWebsiteStockSources(OrderInterface $order): array
    {
        try {
            $store = $this->storeManager->getStore((int) $order->getStoreId());
            $website = $this->storeManager->getWebsite((int) $store->getWebsiteId());
            $websiteCode = (string) $website->getCode();
            $stockId = $this->getAssignedStockIdForWebsite->execute($websiteCode);
            if ($stockId === null) {
                return [];
            }

            $sources = $this->getSourcesForStock->execute((int) $stockId);
            $codes = [];
            foreach ($sources as $source) {
                $code = (string) $source->getSourceCode();
                if ($code !== '' && !in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }

            return $codes;
        } catch (Throwable $e) {
            $this->logger->warning(
                'FulfillmentCore website stock source resolve failed',
                ['order_id' => (int) $order->getEntityId(), 'exception' => $e->getMessage()]
            );
            return [];
        }
    }
}
