<?php
/**
 * MoMo Return (browser redirect) processor.
 *
 * The Notify (IPN) stays the authoritative confirmation; the return URL only
 * decides where the customer lands. A resultCode = 0 landing must not depend
 * on the checkout session still holding its Last* keys though: MoMo redirects
 * can arrive with a fresh or lost session (duplicate return, dropped cookies),
 * which would fail the core SuccessValidator and send a paid customer to the
 * cart page. This service resolves the order from MoMo's own orderId (= the
 * increment id MoMo echoes back), verifies it is a MoMo order, and rebuilds
 * the success session keys the same way core Onepage::saveOrder does.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Model\Ui\ConfigProvider;

/**
 * Processes the MoMo return parameters into a redirect path + session state.
 */
class ReturnProcessor
{
    /**#@+
     * MoMo return parameter names.
     */
    public const RESULT_CODE = 'resultCode';
    public const ORDER_ID = 'orderId';
    /**#@-*/

    /**
     * Redirect targets returned by process().
     */
    public const PATH_SUCCESS = 'checkout/onepage/success';
    public const PATH_CART = 'checkout/cart';

    /**
     * ReturnProcessor constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Turn the MoMo return parameters into a redirect path.
     *
     * ResultCode 0: resolve the order and rebuild the success session so the
     * success page validates even when the checkout session is new. Any other
     * resultCode keeps the historical lenient behaviour (cart, no lookup).
     *
     * @param array $params
     * @return string redirect path
     * @throws LocalizedException customer-safe message when no MoMo order can be resolved
     */
    public function process(array $params): string
    {
        $resultCode = (int)($params[self::RESULT_CODE] ?? -1);
        if ($resultCode !== 0) {
            return self::PATH_CART;
        }

        $order = $this->resolveOrder((string)($params[self::ORDER_ID] ?? ''));
        $this->prepareSuccessSession($order);

        return self::PATH_SUCCESS;
    }

    /**
     * Load the order MoMo reports about.
     *
     * Resolution: the orderId parameter (increment id MoMo echoes back),
     * falling back to the checkout session's last real order id.
     *
     * @param string $incrementId
     * @return OrderInterface
     * @throws LocalizedException when no order matches, or it is not a MoMo order
     */
    private function resolveOrder(string $incrementId): OrderInterface
    {
        if ($incrementId === '') {
            $incrementId = (string)$this->checkoutSession->getLastRealOrderId();
        }
        if ($incrementId === '') {
            throw new LocalizedException(__('MoMo payment could not be matched to an order.'));
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        /** @var OrderInterface|null $order */
        $order = $orders[0] ?? null;

        if ($order === null
            || !$order->getEntityId()
            || $order->getPayment() === null
            || $order->getPayment()->getMethod() !== ConfigProvider::CODE
        ) {
            $this->logger->error(
                'MoMo return could not be matched to a MoMo order.',
                ['orderId' => $incrementId]
            );
            throw new LocalizedException(__('MoMo payment could not be matched to an order.'));
        }

        return $order;
    }

    /**
     * Rebuild the checkout success session keys (mirrors core
     * Onepage::saveOrder / QuoteManagement) so SuccessValidator passes even
     * when the browser arrives with a fresh checkout session. This is the
     * only writer of these keys in the MoMo return flow.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function prepareSuccessSession(OrderInterface $order): void
    {
        try {
            $this->checkoutSession->clearHelperData();
            $this->checkoutSession
                ->setLastQuoteId((int)$order->getQuoteId())
                ->setLastSuccessQuoteId((int)$order->getQuoteId())
                ->setLastOrderId((int)$order->getEntityId())
                ->setLastRealOrderId((string)$order->getIncrementId())
                ->setLastOrderStatus((string)$order->getState());
        } catch (\Exception $e) {
            $this->logger->error('MoMo success session preparation failed: ' . $e->getMessage());
        }
    }
}
