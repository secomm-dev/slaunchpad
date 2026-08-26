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
 * Checkout session stand-in for OrderFinalizer unit tests: the "last order"
 * setters are magic __call methods on the real session and cannot be
 * configured on a PHPUnit mock. The stub keeps them chainable without any
 * session storage.
 */
class SessionStub extends Session
{
    /**
     * No session storage needed in unit tests.
     */
    public function __construct()
    {
    }

    /**
     * @param int $quoteId
     * @return $this
     */
    public function setLastQuoteId($quoteId)
    {
        return $this;
    }

    /**
     * @param int $quoteId
     * @return $this
     */
    public function setLastSuccessQuoteId($quoteId)
    {
        return $this;
    }

    /**
     * @param int $orderId
     * @return $this
     */
    public function setLastOrderId($orderId)
    {
        return $this;
    }

    /**
     * @param string $realOrderId
     * @return $this
     */
    public function setLastRealOrderId($realOrderId)
    {
        return $this;
    }

    /**
     * @param string $status
     * @return $this
     */
    public function setLastOrderStatus($status)
    {
        return $this;
    }
}
