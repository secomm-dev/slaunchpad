<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Controller\Payment;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Adapter\AdapterPool;
use Secomm\PaymentCore\Model\Lifecycle\CanContinuePayment;

/**
 * FEAT-CSWYEJ — Continue Payment / retry (spec §4.8): POST-only
 * (HttpPostActionInterface), CSRF form_key (default form action validation),
 * order ownership, full CanContinuePayment guard, then a fresh provider
 * checkout URL. Action named "retry" — "continue" is a PHP reserved word.
 * Controllers validate + delegate + respond (AGENTS §7.2).
 */
class Retry extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly CustomerSession $customerSession,
        private readonly CanContinuePayment $canContinuePayment,
        private readonly AdapterPool $adapterPool,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $request = $this->getRequest();
        $orderId = (int)$request->getParam('order_id', 0);
        $incrementId = '';

        $failRedirect = function (string $message, ?string $logReason = null) use ($orderId, &$incrementId): Redirect {
            if ($logReason !== null) {
                $this->logger->info(sprintf('[continue_denied] order %s — %s', $incrementId !== '' ? $incrementId : $orderId, $logReason));
            }
            $this->messageManager->addErrorMessage(__($message));
            return $this->resultFactory
                ->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('sales/order/view', ['order_id' => $orderId]);
        };

        if ($orderId <= 0) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sales/order/history');
        }
        try {
            $order = $this->orderRepository->get($orderId);
            $incrementId = (string)$order->getIncrementId();
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('sales/order/history');
        }

        // Ownership — logged-in customer may only continue their own orders (AC-007).
        $customerId = (int)($order->getCustomerId() ?? 0);
        $sessionCustomerId = (int)$this->customerSession->getCustomerId();
        if ($customerId === 0 || $customerId !== $sessionCustomerId) {
            $this->logger->warning(sprintf(
                '[continue_denied] order %s — ownership mismatch (customer %d tried order of %d)',
                $incrementId,
                $sessionCustomerId,
                $customerId
            ));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('customer/account/');
        }

        $reason = $this->canContinuePayment->denyReason($order);
        if ($reason !== CanContinuePayment::REASON_OK) {
            return $failRedirect('This order can no longer be paid.', $reason);
        }
        try {
            $record = $this->paymentRepository->getByOrderId($orderId);
        } catch (NoSuchEntityException) {
            // Guard passed a moment ago — treat as a benign race, deny safely.
            $this->logger->info(sprintf('[continue_denied] order %s — record vanished mid-request', $incrementId));
            return $failRedirect('This order can no longer be paid.', 'no_record');
        }

        try {
            $adapter = $this->adapterPool->getAdapter((string)($order->getPayment()?->getMethod() ?? ''));
            $checkoutUrl = $adapter->getCheckoutUrl($order);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error(sprintf('[continue_denied] order %s — %s', $incrementId, $exception->getMessage()));
            return $failRedirect('Continue Payment is not available for this order.');
        }
        if ($checkoutUrl === null || $checkoutUrl === '') {
            $this->logger->error(sprintf('[continue_denied] order %s — provider returned no checkout URL', $incrementId));
            return $failRedirect('Payment page could not be opened. Please contact us.');
        }

        // DEC-FEATCSWYEJ-003 — a fresh provider session was issued (VNPAY token
        // TTL 15'): restart the record window so (a) the customer is not sent to
        // a dead token later and (b) the cron cannot cancel mid-payment.
        $this->canContinuePayment->refreshExpiry($record, (string)$order->getPayment()?->getMethod());
        $this->logger->info(sprintf('[continue] order %s — redirecting to provider', $incrementId));
        return $this->resultFactory
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setUrl($checkoutUrl);
    }
}
