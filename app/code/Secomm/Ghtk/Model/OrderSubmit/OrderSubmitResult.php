<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

/**
 * Successful GHTK order submission result (SL-016). Carries the snapshot the
 * service persists into the shipment comment — later reads never re-derive
 * the submitted COD amount from grand_total.
 *
 * TASK-BE5YD2 — `$recovered` marks an ORDER_ID_EXIST recovery (same provider
 * order reused after identity validation, no second GHTK order created);
 * `$providerStatus` preserves the raw provider status for diagnostics (the
 * tracking lifecycle remains the tracking pipeline's responsibility).
 */
final class OrderSubmitResult
{
    public function __construct(
        public readonly string $partnerOrderId,
        public readonly string $labelId,
        public readonly string $trackingNumber,
        public readonly float $pickMoney,
        public readonly int $weightGram,
        public readonly bool $recovered = false,
        public readonly ?string $providerStatus = null
    ) {
    }
}
