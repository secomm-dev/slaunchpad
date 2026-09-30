<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;

/**
 * AddToWishlist — fired by wishlist_add_product. The theme adds to wishlist via
 * AJAX (no page reload), so the pixel-side event fires on the next navigation.
 */
class WishlistObserver extends AbstractPendingObserver
{
    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();

        $this->trackAndStash(
            'AddToWishlist',
            $product instanceof Product ? $product : null
        );
    }
}
