<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Checkout\Model\Session;

/**
 * Unit-test stand-in for the checkout session: real methods for the
 * Last* setters (magic __call cannot be configured on PHPUnit mocks) that
 * RECORD every write so tests can prove the success session was rebuilt.
 */
class SessionStub extends Session
{
    /**
     * Recorded Last* writes, keyed by session variable.
     *
     * @var array
     */
    public array $calls = [];

    /**
     * @return void
     */
    public function __construct()
    {
        // Intentionally empty: no session storage in unit tests.
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
