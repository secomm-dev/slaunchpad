<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Model\Pixel;

use Magefan\FacebookPixel\Api\Product\ContentInterface;
use Magefan\FacebookPixel\Model\AbstractPixel;
use Magefan\FacebookPixel\Model\Config;
use Magefan\FacebookPixelPlus\Api\ViewItemListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class ViewItemList extends AbstractPixel implements ViewItemListInterface
{
    /**
     * @var ContentInterface
     */
    private $content;

    /**
     * @var string
     */
    public $itemListName;

    /**
     * ViewItemList constructor.
     *
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ContentInterface $content
     */
    public function __construct(
        Config                      $config,
        StoreManagerInterface       $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        ContentInterface $content
    ) {
        $this->content = $content;
        parent::__construct($config, $storeManager, $categoryRepository);
    }

    /**
     * @inheritDoc
     */
    public function get(array $productItems): array
    {
        $items = [];

        foreach ($productItems as $item) {
            $items[] = $this->content->get($item)['id'];
        }

        return [
            'content_name' => $this->getItemListName(),
            'content_ids' => implode(', ', $items),
            'content_type' => 'product_list',
            'currency' => $this->getCurrentCurrencyCode(),
        ];
    }

    /**
     * Set item list name
     *
     * @param string $name
     */
    public function setItemListName(string $name)
    {
        $this->itemListName = $name;
    }

    /**
     * Get item list name
     *
     * @return string
     */
    public function getItemListName(): string
    {
        return $this->itemListName;
    }
}
