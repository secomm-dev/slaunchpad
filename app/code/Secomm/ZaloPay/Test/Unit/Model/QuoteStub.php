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
 * Quote stand-in for PHPUnit: the data getters used by the payment-first
 * flow are magic __call methods on Quote and cannot be configured on a
 * mock — declaring them as real methods here makes them configurable.
 */
class QuoteStub extends Quote
{
    /**
     * @return bool
     */
    public function getIsActive(): bool
    {
        return parent::getIsActive();
    }

    /**
     * @return int
     */
    public function getItemsCount(): int
    {
        return parent::getItemsCount();
    }

    /**
     * @return float
     */
    public function getGrandTotal(): float
    {
        return parent::getGrandTotal();
    }

    /**
     * @return float
     */
    public function getBaseGrandTotal(): float
    {
        return parent::getBaseGrandTotal();
    }

    /**
     * @return string
     */
    public function getQuoteCurrencyCode(): string
    {
        return parent::getQuoteCurrencyCode();
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return parent::getStoreId();
    }

    /**
     * @return string
     */
    public function getReservedOrderId(): string
    {
        return parent::getReservedOrderId();
    }

    /**
     * @return string|null
     */
    public function getCouponCode(): ?string
    {
        return parent::getCouponCode();
    }

    /**
     * @return string|null
     */
    public function getAppliedRuleIds(): ?string
    {
        return parent::getAppliedRuleIds();
    }
}
