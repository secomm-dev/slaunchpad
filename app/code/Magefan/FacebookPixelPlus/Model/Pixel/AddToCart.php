<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Model\Pixel;

use Magefan\FacebookPixel\Model\AbstractPixel;
use Magefan\FacebookPixel\Model\Config;
use Magefan\FacebookPixelPlus\Api\AddToCartInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Quote\Model\Quote\Item;
use Magefan\FacebookPixel\Api\Cart\ContentInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddToCart extends AbstractPixel implements AddToCartInterface
{
    /**
     * @var ContentInterface
     */
    private $content;

    /**
     * AddToCart constructor.
     *
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ContentInterface $item
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        ContentInterface $item
    ) {
        $this->content = $item;
        parent::__construct($config, $storeManager, $categoryRepository);
    }

    /**
     * @inheritDoc
     */
    public function get(Item $quoteItem): array
    {
        $content = $this->content->get($quoteItem);

        return [
            'AddToCart' => [
                'content_ids' => [$content['id']],
                'content_name' => $quoteItem->getName(),
                'content_type' => 'product',
                'contents' => [
                    $content
                ],
                'currency' => $this->getCurrentCurrencyCode(),
                'value' => $this->formatPrice((float)$quoteItem->getPrice())
            ]
        ];
    }
}
