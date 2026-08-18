<?php declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Tracking;

use Secomm\Ahamove\Model\Carrier\ShippingMethod\Express;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard;
use Secomm\Ahamove\Logger\Logger;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * Bridges Ahamove webhook payloads into the Secomm_ShippingCore tracking pipeline (SL-017).
 */
class WebhookTrackingBridge
{
    public function __construct(
        private readonly CarrierTrackingProcessorInterface $trackingProcessor,
        private readonly AhamoveStatusMapper $statusMapper,
        private readonly Logger $logger
    ) {
    }

    public function process(array $data, string $trackNumber): void
    {
        $status = $data['status'] ?? '';
        $subStatus = null;
        $failComment = null;

        // Extract sub_status and fail_comment from paths if completed/in_process
        if ($status === 'COMPLETED' || $status === 'IN PROCESS') {
            if (isset($data['path'][1]['status']) && isset($data['path'][1]['fail_comment'])) {
                $subStatus = $data['path'][1]['status'];
                $failComment = $data['path'][1]['fail_comment'];
            } elseif (isset($data['path'][0]['status']) && isset($data['path'][0]['fail_comment'])) {
                $subStatus = $data['path'][0]['status'];
                $failComment = $data['path'][0]['fail_comment'];
            }

            // Sometimes sub_status is at the root level for returns
            if (isset($data['sub_status'])) {
                $subStatus = $data['sub_status'];
            }
        } elseif ($status === 'CANCELLED' && isset($data['cancel_comment'])) {
            $failComment = $data['cancel_comment'];
        }

        // Build composite code for the mapper (e.g. "COMPLETED:FAILED")
        $compositeCode = $status;
        if ($subStatus !== null && $subStatus !== '') {
            $compositeCode .= ':' . $subStatus;
        }

        $normalizedStatus = $this->statusMapper->map($compositeCode);

        $message = $status;
        if ($subStatus) {
            $message .= ' - ' . $subStatus;
        }
        if ($failComment) {
            $message .= ' (' . $failComment . ')';
        }

        // Try processing for candidate carrier codes, including full shipping method if needed
        $carrierCandidates = [
            Standard::AHAMOVE_STANDARD_CARRIER_CODE,
            Express::AHAMOVE_EXPRESS_CARRIER_CODE,
            Standard::AHAMOVE_STANDARD_CARRIER_CODE . '_' . Standard::AHAMOVE_STANDARD_CARRIER_CODE,
            Express::AHAMOVE_EXPRESS_CARRIER_CODE . '_' . Express::AHAMOVE_EXPRESS_CARRIER_CODE,
        ];

        $matched = false;
        foreach ($carrierCandidates as $carrierCode) {
            $update = new TrackingUpdate(
                carrierCode: $carrierCode,
                trackingNumber: $trackNumber,
                normalizedStatus: $normalizedStatus,
                carrierStatusCode: $compositeCode,
                carrierStatusMessage: $message,
                occurredAt: null, // Ahamove webhook does not provide a reliable event timestamp in root payload
                source: 'webhook',
                raw: $data
            );

            if ($this->trackingProcessor->process($update)) {
                $matched = true;
                break; // Found and processed the matching track
            }
        }

        if (!$matched) {
            $this->logger->warning('WebhookTrackingBridge: Failed to match any track record in DB for tracking number: ' . $trackNumber);
        }
    }
}
