<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Inventory;

use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;

/**
 * Status-only public availability built on MSI-native salability contracts
 * (plan rev 2 §2b "Availability"): IsProductSalableInterface for single
 * products, AreProductsSalableInterface as the batch seam for search lists
 * (one facade call for N skus — no per-product salability loop in facade
 * code). The stock is resolved once per request.
 *
 * GetProductSalableQtyInterface is deliberately NOT used (TL blocker 2) and
 * quantities are never emitted — only in_stock|out_of_stock.
 */
class Availability
{
    /**
     * @var int|null resolved stock id for the request website
     */
    private ?int $stockId = null;

    /**
     * @param IsProductSalableInterface $isProductSalable single-sku salability
     * @param AreProductsSalableInterface $areProductsSalable batch salability
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite stock id for current website
     */
    public function __construct(
        private readonly IsProductSalableInterface $isProductSalable,
        private readonly AreProductsSalableInterface $areProductsSalable,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
    ) {
    }

    /**
     * Salability status of a single sku.
     *
     * Uses the MSI condition chain, which covers simple/configurable/
     * bundle/grouped per Magento rules.
     *
     * @param string $sku product sku
     * @return string in_stock|out_of_stock
     */
    public function getStatus(string $sku): string
    {
        $salable = $this->isProductSalable->execute($sku, $this->resolveStockId());

        return $salable ? 'in_stock' : 'out_of_stock';
    }

    /**
     * Batch salability statuses for a search result page (single facade call).
     *
     * @param string[] $skus product skus
     * @return array<string, string> sku => in_stock|out_of_stock
     */
    public function getStatuses(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $results = $this->areProductsSalable->execute(array_values($skus), $this->resolveStockId());
        $statuses = [];

        foreach ($results as $index => $result) {
            if (!$result instanceof IsProductSalableResultInterface) {
                continue;
            }
            $statuses[$result->getSku() ?: $skus[$index]] = $result->isSalable() ? 'in_stock' : 'out_of_stock';
        }

        return $statuses;
    }

    /**
     * Resolve the MSI stock id once per request for the store's website.
     *
     * @return int stock id
     */
    private function resolveStockId(): int
    {
        if ($this->stockId === null) {
            $this->stockId = $this->getStockIdForCurrentWebsite->execute();
        }

        return $this->stockId;
    }
}
