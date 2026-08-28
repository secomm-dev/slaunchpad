<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api\Data;

/**
 * Final DTO for inbound OMS status updates applied by InboundUpdateApplier.
 */
final class InboundUpdate
{
    /**
     * @param string $serviceCode Adapter service_code
     * @param string $externalOrderId Remote OMS order id
     * @param string $rawStatus Vendor raw status code
     * @param string|null $eventId Idempotency key; null skips idempotent skip
     * @param string|null $carrierName Carrier display name
     * @param string|null $trackingNumber Tracking number
     * @param string|null $trackingUrl Tracking URL
     */
    public function __construct(
        private readonly string $serviceCode,
        private readonly string $externalOrderId,
        private readonly string $rawStatus,
        private readonly ?string $eventId = null,
        private readonly ?string $carrierName = null,
        private readonly ?string $trackingNumber = null,
        private readonly ?string $trackingUrl = null
    ) {
    }

    public function getServiceCode(): string
    {
        return $this->serviceCode;
    }

    public function getExternalOrderId(): string
    {
        return $this->externalOrderId;
    }

    public function getRawStatus(): string
    {
        return $this->rawStatus;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function getCarrierName(): ?string
    {
        return $this->carrierName;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }
}
