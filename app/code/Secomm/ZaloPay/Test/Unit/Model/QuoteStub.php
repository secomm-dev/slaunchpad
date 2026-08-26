<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model;

use Magento\Quote\Model\Quote;

/**
 * Declares the quote accessors PaymentAttemptManagement reads through
 * AbstractModel's magic __call so PHPUnit can configure them on a mock
 * (magic methods cannot be configured directly).
 */
class QuoteStub extends Quote
{
    /**
     * @return bool
     */
    public function getIsActive(): bool
    {
        return (bool)$this->getData('is_active');
    }

    /**
     * @return int
     */
    public function getItemsCount(): int
    {
        return (int)$this->getData('items_count');
    }

    /**
     * @return float
     */
    public function getGrandTotal(): float
    {
        return (float)$this->getData('grand_total');
    }

    /**
     * @return string
     */
    public function getQuoteCurrencyCode(): string
    {
        return (string)$this->getData('quote_currency_code');
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    /**
     * @return string
     */
    public function getReservedOrderId(): string
    {
        return (string)$this->getData('reserved_order_id');
    }
}
