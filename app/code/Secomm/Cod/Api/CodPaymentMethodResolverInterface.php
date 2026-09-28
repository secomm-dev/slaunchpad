<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Api;

/**
 * TASK-DFGFZ9 (DEC-TASKDFGFZ9-001/003) — COD payment-method identification. SINGLE owner:
 * Secomm_Cod.
 *
 * Supersedes `Secomm\ShippingCore\Api\Cod\CodPaymentMethodResolverInterface` (TASK-STC3NB,
 * arch v4 §4.1) — removed without adapter (DEC-TASKDFGFZ9-001 §4): the single production
 * consumer (Secomm_Ghtk) re-typehinted in the same change and the old contract was never
 * TL-accepted.
 *
 * P1 default (`DefaultCodPaymentMethodResolver`): the Magento core Cash-On-Delivery method
 * code `cashondelivery` (provided by `Magento_OfflinePayments`; the method stays inactive
 * until the merchant enables it). No admin configuration exists for this list — changing the
 * policy happens through a DI preference on this interface (CODRisk future).
 *
 * Consumers must NEVER keep their own COD method list. Identification ONLY — this contract
 * answers "is this payment method COD?" and nothing else; the sibling collection-decision
 * contract (`CodCollectionResolverInterface`, DEC-TASKDFGFZ9-002/003) owns amounts, currency
 * and per-shipment collection policy. The payment method code comparison is exact
 * (case-sensitive, trimmed); an unknown code is simply `false`.
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
