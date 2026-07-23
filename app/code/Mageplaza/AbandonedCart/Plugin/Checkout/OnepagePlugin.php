<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Plugin\Checkout;

use Magento\Checkout\Block\Onepage as CheckoutOnepage;
use Magento\Checkout\Model\Session as CheckoutSession;
use Psr\Log\LoggerInterface;

/**
 * Class OnepagePlugin
 * @package Mageplaza\MultipleCoupons\Plugin\Checkout
 */
class OnepagePlugin
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * OnepagePlugin constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * Add custom value for quote
     *
     * @param CheckoutOnepage $subject
     * @return void
     */
    public function beforeIndex(CheckoutOnepage $subject)
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote->getId()  && !$quote->getData('mp_abandoned_cart_checkout')) {
                $quote->setData('mp_abandoned_cart_checkout', '1');
                $quote->save();
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
