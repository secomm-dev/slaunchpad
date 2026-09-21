<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Rate\CarrierRateInterface;

/**
 * TASK-NAT3YV — immutable, self-guarding realtime carrier rate VO; @see CarrierRateInterface.
 */
final class CarrierRate implements CarrierRateInterface
{
    /**
     * @param float $amount quoted amount (>= 0 — zero is a valid promotional rate)
     * @param string|null $currency ISO code, or null for the store default
     * @throws \LogicException on a negative amount
     */
    public function __construct(
        private readonly float $amount,
        private readonly ?string $currency = null
    ) {
        if ($amount < 0) {
            throw new \LogicException('Carrier rate amount must not be negative.');
        }
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }
}
