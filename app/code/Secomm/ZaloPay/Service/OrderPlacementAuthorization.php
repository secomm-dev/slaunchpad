<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

/**
 * Internal authorization for THE ONE quote -> Sales Order placement the
 * OrderFinalizer performs.
 *
 * "Payment verified" (an attempt in PAID state) is deliberately NOT
 * sufficient to place a ZaloPay quote's order: any generic caller
 * (REST placeOrder, payment-information placeOrder, GraphQL placeOrder,
 * SOAP, stale checkout JS, OSC generic submit) would pass a status-only
 * check. \Secomm\ZaloPay\Plugin\Quote\CartManagementPlaceOrderGuard only
 * lets a call through when it can CONSUME a grant bound to the exact
 * (quote_id, attempt entity_id, app_trans_id) triple — a grant only
 * OrderFinalizer opens, immediately around its own placeOrder() call.
 *
 * Scope: this object is a non-proxy constructor dependency, so the Magento
 * ObjectManager instantiates it once per request/process (frontend, webapi,
 * cron or CLI) — broader than a method call, narrower than a session, and
 * completely internal: no Registry, no session token, no request/query
 * parameter, no admin config secret can create or influence a grant.
 *
 * Single-use: consumeIfMatches() clears the grant as part of a successful
 * FULL-TRIPLE match, so a second generic placeOrder call in the same request
 * finds nothing. OrderFinalizer additionally clear()s in a finally block,
 * covering the case where its placeOrder() call throws before the guard
 * consumed the grant.
 */
class OrderPlacementAuthorization
{
    /**
     * The open grant, or null. Shape: quote_id, attempt_id, app_trans_id.
     *
     * @var array{quote_id: int, attempt_id: int, app_trans_id: string}|null
     */
    private ?array $grant = null;

    /**
     * Open the single-use grant for one finalization.
     *
     * A grant that is still open (never consumed, never cleared) is a
     * programming error: a second grant() throws instead of silently
     * overwriting the pending authorization.
     *
     * @param int $quoteId
     * @param int $attemptId
     * @param string $appTransId
     * @return void
     * @throws \RuntimeException When an unconsumed grant is still open.
     */
    public function grant(int $quoteId, int $attemptId, string $appTransId): void
    {
        if ($this->grant !== null) {
            throw new \RuntimeException(
                sprintf(
                    'ZaloPay order placement authorization already open (quote #%d, attempt #%d); cannot grant quote #%d.',
                    $this->grant['quote_id'],
                    $this->grant['attempt_id'],
                    $quoteId
                )
            );
        }
        $this->grant = [
            'quote_id' => $quoteId,
            'attempt_id' => $attemptId,
            'app_trans_id' => $appTransId,
        ];
    }

    /**
     * Read the open grant for a placeOrder call on the given quote WITHOUT
     * consuming it.
     *
     * The guard's first step: the peek exposes the exact (attempt_id,
     * app_trans_id) binding so the guard can validate the grant against the
     * PERSISTED PaymentAttempt row (attempt exists, its quote_id is the cart
     * being placed, its app_trans_id is the granted one) BEFORE anything is
     * consumed. A quote id alone never authorizes anything.
     *
     * @param int $quoteId
     * @return array{quote_id: int, attempt_id: int, app_trans_id: string}|null
     */
    public function peekForQuote(int $quoteId): ?array
    {
        if ($this->grant === null || $this->grant['quote_id'] !== $quoteId) {
            return null;
        }

        return $this->grant;
    }

    /**
     * Consume the grant only when the FULL triple matches.
     *
     * The guard's consuming step: called ONLY after the persisted attempt
     * backed the grant. A matching grant is consumed (single-use); any
     * other state leaves the grant untouched and refuses.
     *
     * @param int $quoteId
     * @param int $attemptId
     * @param string $appTransId
     * @return bool
     */
    public function consumeIfMatches(int $quoteId, int $attemptId, string $appTransId): bool
    {
        if ($this->grant === null
            || $this->grant['quote_id'] !== $quoteId
            || $this->grant['attempt_id'] !== $attemptId
            || $this->grant['app_trans_id'] !== $appTransId
        ) {
            return false;
        }
        $this->grant = null;

        return true;
    }

    /**
     * Whether a grant is currently open.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->grant !== null;
    }

    /**
     * Drop any open grant (OrderFinalizer's finally block).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->grant = null;
    }
}
