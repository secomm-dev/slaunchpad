<?php

declare(strict_types=1);

namespace Launchpad\QuickCart\Plugin\CustomerData;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session;

/**
 * Exposes coupon data on the customer-data cart section so the cart drawer can
 * render the applied-coupon state and the discount amount across page loads.
 */
class CartSectionCouponData
{
    public function __construct(
        private readonly Session $checkoutSession
    ) {
    }

    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $quote = $this->checkoutSession->getQuote();

        $result['coupon_code'] = (string) ($quote->getCouponCode() ?? '');

        // Sum the persisted address discount (negative value, stored when the quote
        // was saved after a totals collection). Do NOT re-collect totals here — the
        // section request has no request context for a full totals collection.
        $discount = 0.0;
        foreach ($quote->getAllAddresses() as $address) {
            $discount += (float) $address->getDiscountAmount();
        }
        $result['discount_amount'] = $discount;

        return $result;
    }
}
