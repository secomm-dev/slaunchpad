<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Model;

use Secomm\Cod\Api\CodCollectionAttemptInterface;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003) — plain attempt-identity VO; carriers construct
 * it directly from their deterministic idempotency key (no factories in Secomm modules).
 */
final class CodCollectionAttempt implements CodCollectionAttemptInterface
{
    public function __construct(
        private readonly string $carrierCode,
        private readonly string $providerShipmentReference
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCarrierCode(): string
    {
        return $this->carrierCode;
    }

    /**
     * @inheritDoc
     */
    public function getProviderShipmentReference(): string
    {
        return $this->providerShipmentReference;
    }
}
