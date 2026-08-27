<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magefan\FacebookPixel\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magefan\FacebookPixelPlus\Model\AddToCartRegistry;

class ProductAddToCartAfter implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var AddToCartRegistry
     */
    private $addToCartRegistry;

    /**
     * ProductAddToCartAfter constructor.
     * @param Config $config
     * @param AddToCartRegistry $addToCartRegistry
     */
    public function __construct(
        Config $config,
        AddToCartRegistry $addToCartRegistry
    ) {
        $this->config = $config;
        $this->addToCartRegistry = $addToCartRegistry;
    }

    /**
     * Set data after add product to cart
     *
     * @param Observer $observer
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if ($this->config->isEnabled()) {
            $this->addToCartRegistry->addItem($observer->getData('quote_item'));
        }
    }
}
