<?php
/**
 * Unit-test stand-in for the checkout session.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Checkout\Model\Session;

/**
 * Real methods for the Last* setters and getters (magic __call cannot be
 * configured on PHPUnit mocks) that RECORD every write so tests can prove
 * the success session was rebuilt — and that nothing was written when it
 * must not be.
 */
class SessionStub extends Session
{
    /**
     * Recorded session operations, keyed by session variable.
     *
     * @var array
     */
    public array $calls = [];

    /**
     * Configurable last real order id (increment id) held by the session.
     *
     * @var string|null
     */
    public ?string $lastRealOrderId = null;

    /**
     * How many times the last real order id was read.
     *
     * @var int
     */
    public int $lastRealOrderIdReads = 0;

    /**
     * Intentionally empty: no session storage in unit tests.
     */
    public function __construct()
    {
    }

    /**
     * Record the helper-data reset instead of clearing real storage.
     *
     * @return $this
     */
    public function clearHelperData()
    {
        $this->calls['clearHelperData'] = true;

        return $this;
    }

    /**
     * Serve the configured increment id.
     *
     * @return string|null
     */
    public function getLastRealOrderId()
    {
        $this->lastRealOrderIdReads++;

        return $this->lastRealOrderId;
    }

    /**
     * @param int|string $quoteId
     * @return $this
     */
    public function setLastQuoteId($quoteId): static
    {
        $this->calls['last_quote_id'] = $quoteId;

        return $this;
    }

    /**
     * @param int|string $quoteId
     * @return $this
     */
    public function setLastSuccessQuoteId($quoteId): static
    {
        $this->calls['last_success_quote_id'] = $quoteId;

        return $this;
    }

    /**
     * @param int|string $orderId
     * @return $this
     */
    public function setLastOrderId($orderId): static
    {
        $this->calls['last_order_id'] = $orderId;

        return $this;
    }

    /**
     * @param string $realOrderId
     * @return $this
     */
    public function setLastRealOrderId(string $realOrderId): static
    {
        $this->calls['last_real_order_id'] = $realOrderId;

        return $this;
    }

    /**
     * @param string $status
     * @return $this
     */
    public function setLastOrderStatus(string $status): static
    {
        $this->calls['last_order_status'] = $status;

        return $this;
    }
}
