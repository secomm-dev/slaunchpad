<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class RestoreQuoteObserver implements ObserverInterface
{
    public function __construct(
        protected QuoteFactory         $quoteFactory,
        protected Session              $session,
        protected RedirectFactory      $redirectFactory,
        protected CartRepositoryInterface $cartRepository,
        protected OrderRepositoryInterface $orderRepository
    ) {
    }

    public function execute(Observer $observer): void
    {
        $lastQuoteId = $this->session->getLastQuoteId();
        $lastOrderId = $this->session->getLastOrderId();

        if (!$lastQuoteId || !$lastOrderId) {
            return;
        }

        try {
            $order = $this->orderRepository->get($lastOrderId);
            $paymentMethod = $order->getPayment()->getMethod();
            $orderStatus = $order->getStatus();

            // Restore quote for ZaloPay pending payment
            if ($paymentMethod === 'zalopay' && $orderStatus === 'pending_payment') {
                $this->session->restoreQuote();
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Order not found - skip restore
            return;
        }
    }
}
