<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Model;

use Secomm\Cod\Api\CodPaymentMethodResolverInterface;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003) — Launchpad P1 COD identification: the Magento
 * core Cash-On-Delivery payment method (`cashondelivery`, provided by `Magento_OfflinePayments`
 * — the method stays inactive until the merchant enables it; no custom payment method is
 * created and no admin configuration exists for this list).
 *
 * Comparison is exact and case-sensitive after trimming. Replacing this policy (e.g. a future
 * CODRisk consumer adding more methods) happens through a DI preference on
 * {@see CodPaymentMethodResolverInterface} — no code change here.
 */
final class DefaultCodPaymentMethodResolver implements CodPaymentMethodResolverInterface
{
    public const DEFAULT_COD_METHODS = ['cashondelivery'];

    /**
     * @inheritDoc
     */
    public function isCod(string $paymentMethodCode): bool
    {
        return in_array(trim($paymentMethodCode), self::DEFAULT_COD_METHODS, true);
    }
}
