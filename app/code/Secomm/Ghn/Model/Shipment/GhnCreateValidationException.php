<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;

/**
 * TASK-9Q5ZAK (GHN-D) — local pre-flight validation failure for the CREATE flow, carrying the
 * normalized reason token (INVALID_PARCEL | INVALID_CONFIGURATION) so the creation service can
 * persist the right diagnostic without parsing messages. Always a business (UNAVAILABLE-shaped)
 * rejection decided BEFORE any provider call.
 */
class GhnCreateValidationException extends LocalizedException
{
    public const REASON_INVALID_PARCEL = 'INVALID_PARCEL';
    public const REASON_INVALID_CONFIGURATION = 'INVALID_CONFIGURATION';

    public function __construct(
        private readonly string $reasonToken,
        \Magento\Framework\Phrase $phrase
    ) {
        parent::__construct($phrase);
    }

    public function getReasonToken(): string
    {
        return $this->reasonToken;
    }
}
