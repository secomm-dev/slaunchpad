<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\MoMo\Plugin\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Service\OrderPlacementAuthorization;

/**
 * Server-side guard: a MoMo quote may only become a Sales Order through the
 * exact internal placement authorized by OrderFinalizer (MOMO-01).
 *
 * Blocks the generic customer-facing paths — REST placeOrder
 * (POST /V1/carts[/guest-carts]/...[/order] and POST .../payment-information),
 * GraphQL placeOrder, SOAP checkoutCartPlaceOrder, stale checkout JS and
 * Mageplaza OSC's generic submit — for quotes whose payment method is MoMo,
 * in EVERY area the plugin is compiled for (frontend, webapi_rest, graphql,
 * webapi_soap: it is registered in the global etc/di.xml).
 *
 * Scope guarantees:
 *  - non-MoMo quotes return untouched (Magento behaviour unchanged);
 *  - admin order creation is NOT affected: the admin UI places orders via
 *    Magento\Sales\Model\AdminOrder\Create::createOrder() ->
 *    QuoteManagement::submit(), which this guard does not intercept
 *    (verified at base 87f4db44, vendor/magento/module-sales/Model/
 *    AdminOrder/Create.php:2071);
 *  - the single pass-through is OrderFinalizer's own call, whose grant is
 *    validated against the PERSISTED PaymentAttempt before consumption:
 *    the grant must bind the placed cart id to an attempt row that REALLY
 *    exists with that same quote_id AND that same order_ref (full triple) —
 *    a quote id, a PAID status, a session or a browser input alone never
 *    authorizes anything (mirrors ZaloPay review TASK-EDS9T5 Blocker 3).
 *
 * Construction isolation (issue #20 / BUG-QFR2AY): the plugin is GLOBAL on
 * QuoteManagement::placeOrder(), so it is built for EVERY payment method's
 * placement. Its constructor therefore depends on nothing MoMo-only:
 * method discrimination compares against an ObjectManager-injected method
 * code string (the MoMoFacade Adapter is NOT needed to compare a string),
 * and the attempt repository is wired as a Proxy in etc/di.xml, so the real
 * repository (and its ResourceConnection graph) is constructed only when a
 * quote is CONFIRMED as MoMo. A construction/DI failure in this module can
 * no longer abort another payment method's order placement.
 */
class CartManagementPlaceOrderGuard
{
    /**
     * CartManagementPlaceOrderGuard constructor.
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentAttemptRepositoryInterface $attemptRepository
     *         (wired as Secomm\MoMo\Model\PaymentAttemptRepository\Proxy)
     * @param string $methodCode the MoMo method code (momo_payment)
     * @param OrderPlacementAuthorization $authorization
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly PaymentAttemptRepositoryInterface $attemptRepository,
        private readonly string $methodCode,
        private readonly OrderPlacementAuthorization $authorization,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Refuse generic placement of a MoMo quote without an internal grant
     * backed by the persisted attempt triple.
     *
     * @param CartManagementInterface $subject
     * @param int|string $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface|null $paymentMethod
     * @return array|null
     * @throws LocalizedException When the quote is a MoMo quote and no
     *         exact, persisted-attempt-backed authorization is consumable
     *         for it.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforePlaceOrder(CartManagementInterface $subject, $cartId, $paymentMethod = null): ?array
    {
        $quoteId = (int)$cartId;
        $quote = $this->loadQuote($quoteId);
        if ($quote === null || !$this->isMoMoQuote($quote)) {
            return null;
        }

        $grant = $this->authorization->peekForQuote($quoteId);
        if ($grant === null || !$this->isGrantBackedByPersistedAttempt($grant, $quoteId)) {
            $this->logger->critical(
                'MoMo placeOrder guard: blocked unauthorized order placement for a MoMo quote.',
                ['quote_id' => $quoteId]
            );
            throw new LocalizedException(
                __(
                    'The MoMo order can only be created after the payment is verified. '
                    . 'Please complete the MoMo payment.'
                )
            );
        }

        // Full triple re-checked atomically at consumption (single-use).
        if (!$this->authorization->consumeIfMatches(
            $quoteId,
            (int)$grant['attempt_id'],
            (string)$grant['order_ref']
        )) {
            $this->logger->critical(
                'MoMo placeOrder guard: grant vanished between peek and consume.',
                ['quote_id' => $quoteId, 'attempt_id' => (int)$grant['attempt_id']]
            );
            throw new LocalizedException(
                __(
                    'The MoMo order can only be created after the payment is verified. '
                    . 'Please complete the MoMo payment.'
                )
            );
        }

        return null;
    }

    /**
     * Whether the grant is backed by the PERSISTED attempt: the attempt row
     * must exist and carry BOTH the granted order_ref AND the quote id being
     * placed. Guards against quote-only grants, stale/forged grants and
     * attempt-id smuggling.
     *
     * @param array $grant {quote_id, attempt_id, order_ref}
     * @param int $quoteId
     * @return bool
     */
    private function isGrantBackedByPersistedAttempt(array $grant, int $quoteId): bool
    {
        try {
            $attempt = $this->attemptRepository->getById((int)$grant['attempt_id']);
        } catch (NoSuchEntityException $exception) {
            return false;
        }

        return (int)$attempt->getQuoteId() === $quoteId
            && (string)$attempt->getOrderRef() === (string)$grant['order_ref'];
    }

    /**
     * Load the quote being placed (null when it does not exist — the core
     * call raises its own NoSuchEntityException for that case).
     *
     * @param int $quoteId
     * @return Quote|null
     */
    private function loadQuote(int $quoteId): ?Quote
    {
        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (NoSuchEntityException $exception) {
            return null;
        }

        return $quote instanceof Quote ? $quote : null;
    }

    /**
     * Whether the quote is currently paying with MoMo.
     *
     * @param Quote $quote
     * @return bool
     */
    private function isMoMoQuote(Quote $quote): bool
    {
        $payment = $quote->getPayment();

        return $payment !== null && $payment->getMethod() === $this->methodCode;
    }
}
