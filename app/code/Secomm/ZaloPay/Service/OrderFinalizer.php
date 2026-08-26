<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;

/**
 * Idempotent creation of the Magento Order from a VERIFIED paid attempt
 * (ZALOPAY-PAYMENT-FIRST Phase 1).
 *
 * Runs inside one DB transaction with the attempt row locked
 * (SELECT ... FOR UPDATE on app_trans_id):
 *  - already FINALIZED  -> return the bound order (no duplicate order);
 *  - otherwise          -> place the order from the QUOTE (CartManagement
 *                          uses the ACTIVE quote, so a second placement of a
 *                          submitted quote raises NoSuchEntity and we recover
 *                          by reserved_order_id), bind order_id, transition
 *                          to FINALIZED (terminal), capture PENDING_PAYMENT.
 *
 * "One provider transaction never creates two Magento orders" is enforced by
 * the row lock + the terminal FINALIZED state + the unique order_id column.
 */
class OrderFinalizer
{
    /**
     * OrderFinalizer constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param CartManagementInterface $cartManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigInterface $config
     * @param ResourceConnection $resourceConnection
     * @param Session $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly CartManagementInterface           $cartManagement,
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly SearchCriteriaBuilder             $searchCriteriaBuilder,
        private readonly ConfigInterface                   $config,
        private readonly ResourceConnection                $resourceConnection,
        private readonly Session                           $checkoutSession,
        private readonly LoggerInterface                   $logger
    ) {
    }

    /**
     * Place, bind and capture the order for a verified-paid attempt.
     *
     * @param PaymentAttemptInterface $attempt Attempt in ACTIVE or PAID state.
     * @param string $providerTransactionId ZaloPay zp_trans_id (may be empty when unavailable).
     * @return OrderInterface The bound order (existing one on duplicate finalize).
     * @throws LocalizedException
     */
    public function finalize(PaymentAttemptInterface $attempt, string $providerTransactionId = ''): OrderInterface
    {
        $appTransId = (string)$attempt->getAppTransId();
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $locked = $this->repository->lockByAppTransId($appTransId);
            if ($locked === null) {
                throw new LocalizedException(
                    __('ZaloPay payment attempt "%1" disappeared during finalization.', $appTransId)
                );
            }

            if ($locked->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
                $connection->commit();
                /** @var OrderInterface $existing */
                $existing = $this->orderRepository->get((int)$locked->getOrderId());
                $this->prepareSuccessSession($attempt, $existing);

                return $existing; // Duplicate return/callback: same resulting state.
            }

            if (!$locked->canTransitionTo(PaymentAttemptInterface::STATUS_FINALIZED)) {
                throw new LocalizedException(
                    __(
                        'ZaloPay attempt "%1" cannot be finalized from state "%2".',
                        $appTransId,
                        $locked->getPaymentStatus()
                    )
                );
            }

            $order = $locked->getOrderId()
                ? $this->orderRepository->get((int)$locked->getOrderId())
                : $this->placeOrderForAttempt($locked);

            if ($providerTransactionId !== '') {
                $locked->setProviderTransactionId($providerTransactionId);
            }
            $locked->markFinalized((int)$order->getEntityId());
            $this->repository->save($locked);

            $this->captureOrder($order, $appTransId, $providerTransactionId);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->prepareSuccessSession($attempt, $order);

        return $order;
    }

    /**
     * Place the order from the attempt's quote. Payment method and guest email
     * were persisted on the quote by the frontend set-payment-information step.
     *
     * @param PaymentAttemptInterface $attempt
     * @return OrderInterface
     * @throws LocalizedException
     */
    private function placeOrderForAttempt(PaymentAttemptInterface $attempt): OrderInterface
    {
        try {
            $orderId = $this->cartManagement->placeOrder((int)$attempt->getQuoteId());

            return $this->orderRepository->get((int)$orderId);
        } catch (NoSuchEntityException $e) {
            // The quote is no longer active: it was already submitted by a
            // concurrent finalizer (or an order-first path). Recover the
            // existing order through the reserved increment id instead of
            // creating a second one.
            $order = $this->findOrderByIncrementId((string)$attempt->getReservedOrderId());
            if ($order === null) {
                throw new LocalizedException(
                    __(
                        'Cannot place order for ZaloPay attempt "%1": the quote is inactive and no order was found.',
                        $attempt->getAppTransId()
                    )
                );
            }

            return $order;
        }
    }

    /**
     * Capture a PENDING_PAYMENT order (mirrors UpdateOrderCommand semantics;
     * here the capture is driven by the authoritative v2/query verification).
     *
     * @param OrderInterface $order
     * @param string $appTransId
     * @param string $providerTransactionId
     * @return void
     * @throws LocalizedException
     */
    private function captureOrder(OrderInterface $order, string $appTransId, string $providerTransactionId): void
    {
        if ($order->getState() !== \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
            return;
        }
        $payment = $order->getPayment();
        ContextHelper::assertOrderPayment($payment);
        if ($providerTransactionId !== '') {
            $payment->setTransactionId($providerTransactionId);
        }
        if ($this->config->getValue('payment_action') === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            $payment->capture();
        }
        $message = __('ZaloPay payment verified via v2/query (app_trans_id: %1).', $appTransId);
        $payment->prependMessage($message);
        /** @var \Magento\Sales\Model\Order $order */
        $order->addCommentToStatusHistory($message);
        $this->orderRepository->save($order);
    }

    /**
     * Mirror the core Onepage::saveOrder session updates so the standard
     * success page validates after the payment-first redirect flow.
     *
     * @param PaymentAttemptInterface $attempt
     * @param OrderInterface $order
     * @return void
     */
    private function prepareSuccessSession(PaymentAttemptInterface $attempt, OrderInterface $order): void
    {
        try {
            $this->checkoutSession
                ->setLastQuoteId($attempt->getQuoteId())
                ->setLastSuccessQuoteId($attempt->getQuoteId())
                ->setLastOrderId((int)$order->getEntityId())
                ->setLastRealOrderId((string)$order->getIncrementId())
                ->setLastOrderStatus((string)$order->getState());
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay success session preparation failed: ' . $e->getMessage());
        }
    }

    /**
     * @param string $incrementId
     * @return OrderInterface|null
     */
    private function findOrderByIncrementId(string $incrementId): ?OrderInterface
    {
        if ($incrementId === '') {
            return null;
        }
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        return $orders ? reset($orders) : null;
    }
}
