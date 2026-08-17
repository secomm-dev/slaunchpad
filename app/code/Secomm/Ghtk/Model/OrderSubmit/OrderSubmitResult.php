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
 */
final class OrderSubmitResult
{
    public function __construct(
        public readonly string $partnerOrderId,
        public readonly string $labelId,
        public readonly string $trackingNumber,
        public readonly float $pickMoney,
        public readonly int $weightGram
    ) {
    }
}
