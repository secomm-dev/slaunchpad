<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Tracking;

/**
 * A single carrier tracking observation, carrier-normalized (SL-017 /
 * DEC-SL017-001). Built by the CARRIER side (webhook parser or Tracking API
 * fetcher) which also fills the normalized status via its own
 * CarrierStatusMapperInterface — webhook and polling then feed the SAME
 * CarrierTrackingProcessorInterface pipeline.
 *
 * Raw carrier fields are carried alongside the normalized values so nothing
 * is lost for debugging/reconciliation.
 */
interface TrackingUpdateInterface
{
    public function getCarrierCode(): string;

    /**
     * The identifier stored on the Magento shipment track (track_number).
     */
    public function getTrackingNumber(): string;

    /**
     * @return string NormalizedTrackingStatus constant the carrier mapper resolved.
     */
    public function getNormalizedStatus(): string;

    public function getCarrierStatusCode(): ?string;

    public function getCarrierStatusMessage(): ?string;

    /**
     * Carrier-side observation time (unix ts) when the carrier reported it —
     * used for out-of-order protection. Null when the carrier provides none.
     */
    public function getOccurredAt(): ?int;

    /**
     * @return string 'webhook' | 'api' (reconciliation source tag).
     */
    public function getSource(): string;

    /**
     * @return array<string, mixed> Sanitized raw carrier payload (no secrets/PII).
     */
    public function getRaw(): array;
}
