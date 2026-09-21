<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Cod;

/**
 * TASK-STC3NB (address-shipping architecture Revision v4 §4.1) — COD payment identification.
 *
 * ShippingCore owns the CONFIGURATION declaring which Magento payment method codes are treated
 * as COD, plus this provider-neutral resolver. Carrier modules (GHN/GHTK) consume the answer
 * when building provider shipment/order requests:
 *
 *     $order->getPayment()->getMethod()  →  isCod($methodCode)  →  map provider COD fields
 *
 * Identification ONLY — this contract never expresses COD eligibility, amounts, surcharges, or
 * any payment policy. The payment method code comparison is exact (case-sensitive, trimmed);
 * an unknown/unconfigured code is simply `false`.
 */
interface CodPaymentMethodResolverInterface
{
    /**
     * Whether the given Magento payment method code is configured as a COD payment method.
     *
     * @param string $paymentMethodCode Magento payment method code (trimmed before comparison)
     */
    public function isCod(string $paymentMethodCode): bool;
}
