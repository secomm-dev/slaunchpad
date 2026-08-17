<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Tracking;

use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;

/**
 * Immutable TrackingUpdateInterface DTO — see the interface for semantics.
 */
final class TrackingUpdate implements TrackingUpdateInterface
{
    /**
     * @param array<string, mixed> $raw Sanitized raw carrier payload.
     */
    public function __construct(
        private readonly string $carrierCode,
        private readonly string $trackingNumber,
        private readonly string $normalizedStatus,
        private readonly ?string $carrierStatusCode,
        private readonly ?string $carrierStatusMessage,
        private readonly ?int $occurredAt,
        private readonly string $source,
        private readonly array $raw = []
    ) {
    }

    public function getCarrierCode(): string
    {
        return $this->carrierCode;
    }

    public function getTrackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function getNormalizedStatus(): string
    {
        return $this->normalizedStatus;
    }

    public function getCarrierStatusCode(): ?string
    {
        return $this->carrierStatusCode;
    }

    public function getCarrierStatusMessage(): ?string
    {
        return $this->carrierStatusMessage;
    }

    public function getOccurredAt(): ?int
    {
        return $this->occurredAt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }
}
