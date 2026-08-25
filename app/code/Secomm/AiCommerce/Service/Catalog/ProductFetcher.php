<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Secomm\AiCommerce\Service\InvalidParameterException;
use Secomm\AiCommerce\Service\Inventory\Availability;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\Response\ProductDto;

/**
 * Public product lookup. ProductRepositoryInterface::get() alone is NOT
 * proof of public availability — public eligibility is verified explicitly:
 * status enabled, storefront visibility, website assignment of the store.
 * Disabled / non-visible / unassigned and nonexistent SKUs all yield the
 * SAME deterministic 404 (existence of non-public products is never leaked
 * through differentiated errors).
 */
class ProductFetcher
{
    private const SKU_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/';

    /**
     * @param ProductRepositoryInterface $productRepository product repository
     * @param Availability $availability status-only salability
     * @param ProductDto $productDto product DTO builder
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Availability $availability,
        private readonly ProductDto $productDto
    ) {
    }

    /**
     * Fetch a publicly available product and build its detail DTO.
     *
     * @param string $sku raw sku route segment
     * @param StoreInterface $store resolved store view
     * @return mixed[] product DTO array
     * @throws InvalidParameterException on malformed sku
     * @throws NotFoundException on nonexistent/non-public products
     */
    public function fetch(string $sku, StoreInterface $store): array
    {
        $sku = trim($sku);

        if (!preg_match(self::SKU_PATTERN, $sku)) {
            throw new InvalidParameterException(__('Invalid request parameters.'));
        }

        try {
            $product = $this->productRepository->get($sku, false, (int) $store->getId(), false);
        } catch (NoSuchEntityException $exception) {
            throw new NotFoundException(__('Resource not found.'));
        }

        if (!$this->isPubliclyAvailable($product, $store)) {
            throw new NotFoundException(__('Resource not found.'));
        }

        return $this->productDto->toArray($product, $store, $this->availability->getStatus($sku));
    }

    /**
     * Public eligibility: enabled, storefront-visible, website-assigned.
     *
     * @param ProductInterface $product product entity
     * @param StoreInterface $store resolved store view
     * @return bool true when publicly available in this store
     */
    private function isPubliclyAvailable(ProductInterface $product, StoreInterface $store): bool
    {
        $status = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;

        if ((int) $product->getStatus() !== $status) {
            return false;
        }

        $visibility = (int) $product->getVisibility();
        $allowed = [
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ];

        if (!in_array($visibility, $allowed, true)) {
            return false;
        }

        $websiteIds = array_map('intval', (array) $product->getWebsiteIds());

        return in_array((int) $store->getWebsiteId(), $websiteIds, true);
    }
}
