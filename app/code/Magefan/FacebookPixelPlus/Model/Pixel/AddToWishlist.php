<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Model\Pixel;

use Magefan\FacebookPixel\Model\AbstractPixel;
use Magefan\FacebookPixel\Model\Config;
use Magefan\FacebookPixelPlus\Api\AddToWishlistInterface;
use Magefan\FacebookPixelPlus\Api\Wishlist\ContentInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Model\Item;

class AddToWishlist extends AbstractPixel implements AddToWishlistInterface
{
    /**
     * @var ContentInterface
     */
    private $content;

    /**
     * AddToWishlist constructor.
     *
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ContentInterface $content
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        ContentInterface $content
    ) {
        $this->content = $content;
        parent::__construct($config, $storeManager, $categoryRepository);
    }

    /**
     * @inheritDoc
     */
    public function get(Item $wishlistItem): array
    {
        $product = $wishlistItem->getProduct();
        $content = $this->content->get($wishlistItem);
        $categoryNames = $this->getCategoryNames($product);

        return [
            'AddToWishlist' => [
                'content_ids' => [$content['id']],
                'content_category' => implode('/', $categoryNames),
                'content_name' => $product->getName(),
                'contents' => [
                    $content
                ],
                'currency' => $this->getCurrentCurrencyCode(),
                'value' => $this->getPrice($product)
            ]
        ];
    }
}
