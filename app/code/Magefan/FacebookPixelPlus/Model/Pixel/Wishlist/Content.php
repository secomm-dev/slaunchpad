<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Model\Pixel\Wishlist;

use Magefan\FacebookPixel\Model\AbstractPixel;
use Magefan\FacebookPixelPlus\Api\Wishlist\ContentInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Wishlist\Model\Item;

class Content extends AbstractPixel implements ContentInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @inheritDoc
     */
    public function get(Item $wishlistItem): array
    {
        $product = $this->getItemProduct($wishlistItem);
        return [
            'id' => ($this->config->getProductAttribute() == 'sku')
                ? $product->getSku()
                : $product->getData($this->config->getProductAttribute()),
            'quantity' => $wishlistItem->getQty() * 1
        ];
    }

    /**
     * Get product from wishlist item
     *
     * @param Item $wishlistItem
     * @return \Magento\Catalog\Api\Data\ProductInterface
     */
    protected function getItemProduct(Item $wishlistItem)
    {
        $product = $wishlistItem->getProduct();
        if ('configurable' === $product->getTypeId()) {
            $options = $wishlistItem->getOptions();
            if ($options) {
                foreach ($options as $option) {
                    if ($option->getCode() === 'simple_product') {
                        try {
                            $product = $this->getProductRepository()->getById($option->getProductId());
                            break;
                        } catch (NoSuchEntityException $e) {
                            return $product;
                        }
                    }
                }
            }
        }
        return $product;
    }

    /**
     * Get product repository instance
     *
     * @return ProductRepositoryInterface
     */
    protected function getProductRepository(): ProductRepositoryInterface
    {
        if (null === $this->productRepository) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        }
        return $this->productRepository ;
    }
}
