<?php

declare(strict_types=1);

namespace Launchpad\Homepage\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Card gallery images (TASK-0NNZCW v4.7, SLP-213).
 *
 * Listing collections that skip media gallery data (third-party collection
 * replacements) leave getMediaGalleryImages() empty — this view model falls
 * back to loading the gallery for that product on demand. Batched data from
 * addMediaGalleryData() is reused as-is.
 */
class ProductGallery implements ArgumentInterface
{
    private ProductRepositoryInterface $productRepository;

    private StoreManagerInterface $storeManager;

    /** Temporary diagnostics (v4.7) — remove after QC. */
    private string $lastError = '';

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function __construct(
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Gallery images for the card slider, capped for the listing layout.
     *
     * @return \Magento\Framework\Data\Collection|null
     */
    public function getSliderImages(Product $product, int $limit = 4)
    {
        $images = $product->getMediaGalleryImages();
        if (!$images || $images->count() < 2) {
            // The listing collection skipped gallery data (third-party
            // collection) — reload the product fully, which carries the media
            // gallery (proven path; the gallery read handler can throw on
            // third-party collection items).
            try {
                $full = $this->productRepository->getById(
                    (int) $product->getId(),
                    false,
                    (int) $this->storeManager->getStore()->getId()
                );
                $images = $full->getMediaGalleryImages();
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();
            }
        }

        return $images;
    }
}
