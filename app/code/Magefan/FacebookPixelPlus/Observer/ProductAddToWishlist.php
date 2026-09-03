<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Observer;

use Magefan\FacebookPixel\Model\Config;
use Magefan\FacebookPixelPlus\Api\SessionManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magefan\FacebookPixelPlus\Api\AddToWishlistInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductAddToWishlist implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var AddToWishlistInterface
     */
    private $addToWishlist;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * ProductAddToWishlistAfter constructor.
     *
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param AddToWishlistInterface $addToWishlist
     * @param SessionManagerInterface $sessionManager
     */
    public function __construct(
        Config $config,
        CheckoutSession $checkoutSession,
        AddToWishlistInterface $addToWishlist,
        SessionManagerInterface $sessionManager
    ) {
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->addToWishlist = $addToWishlist;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Set FB pixel data on add product to wishlist
     *
     * @param Observer $observer
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if ($this->config->isEnabled()) {
            $wishlistItem = $observer->getData('item');
            $this->sessionManager->push(
                $this->checkoutSession,
                $this->addToWishlist->get($wishlistItem)
            );
        }
    }
}
