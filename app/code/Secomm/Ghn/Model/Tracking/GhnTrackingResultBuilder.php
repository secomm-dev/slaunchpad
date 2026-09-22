<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Tracking;

use Magento\Framework\Phrase;
use Magento\Shipping\Model\Tracking\Result;
use Magento\Shipping\Model\Tracking\Result\Error;
use Magento\Shipping\Model\Tracking\Result\Status;
use Secomm\Ghn\Api\Exception\GhnApiException;
use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Model\Tracking\ShipmentTrackingProcessor;

/**
 * TASK-PWHG0V (GHN-E3-A) — adapts the E1 tracking pipeline (one fetcher, ONE
 * {@see GhnStatusMapper}) into the Magento carrier tracking contract.
 *
 * Failure semantics (brief §10): provider not-found AND transport/5xx failures both resolve to a
 * safe `Error` result — the Shipment View popup renders Magento's generic "unavailable" row, no
 * exception ever escapes. Success optionally feeds the SAME `TrackingUpdate` through the
 * ShippingCore processor (brief §11) — display and normalized-state reconciliation share one
 * provider query; NO parallel persistence path is introduced (the processor is the only writer).
 *
 * Display contract (brief §7/§8): normalized status label (i18n) plus the provider raw status in
 * parentheses; curated progress entries (status label + time — no raw payload, no PII). No
 * tracking URL is emitted until an official public GHN tracking URL is docs-verified (brief §9).
 */
class GhnTrackingResultBuilder
{
    /** Operational labels per normalized status — display only; taxonomy stays in ShippingCore. */
    private const LABELS = [
        NormalizedTrackingStatus::CREATED => 'Created',
        NormalizedTrackingStatus::PICKING => 'Picking up',
        NormalizedTrackingStatus::PICKED_UP => 'Picked up',
        NormalizedTrackingStatus::IN_TRANSIT => 'In transit',
        NormalizedTrackingStatus::OUT_FOR_DELIVERY => 'Out for delivery',
        NormalizedTrackingStatus::DELIVERED => 'Delivered',
        NormalizedTrackingStatus::DELIVERY_FAILED => 'Delivery failed',
        NormalizedTrackingStatus::LOST => 'Lost',
        NormalizedTrackingStatus::DAMAGED => 'Damaged',
        NormalizedTrackingStatus::RETURNING => 'Returning to sender',
        NormalizedTrackingStatus::RETURNED => 'Returned to sender',
        NormalizedTrackingStatus::CANCELLED => 'Cancelled',
        NormalizedTrackingStatus::UNKNOWN => 'Unknown',
    ];

    public function __construct(
        private readonly GhnTrackingFetcher $fetcher,
        private readonly GhnStatusMapper $statusMapper,
        private readonly ShipmentTrackingProcessor $trackingProcessor
    ) {
    }

    /**
     * @param string $carrierTitle display title from the carrier config (`getConfigData('title')`)
     * @return Result|false Magento tracking result; false only for a blank tracking number
     */
    public function build(string $trackingNumber, string $carrierTitle): Result|false
    {
        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') {
            return false;
        }

        try {
            $update = $this->fetcher->fetch($trackingNumber);
        } catch (GhnApiException $fetchFailure) {
            // Provider/API failure (timeout, remote outage, rate limit, auth, invalid request) →
            // safe Magento Error result. Programming errors (TypeError, …) propagate fail-loud —
            // never masked as a provider outage.
            return $this->errorResult($trackingNumber, $carrierTitle, $fetchFailure);
        }

        if ($update === null) {
            // Provider answered but nothing usable (unknown order / empty status) — safe not-found.
            return $this->errorResult($trackingNumber, $carrierTitle, null);
        }

        // Reconcile through the E1 pipeline (occurrence-aware processor; no-op when the state row
        // already reflects this status). Reconciliation is best-effort; provider/runtime
        // Exceptions are non-fatal. PHP Errors/TypeErrors must remain visible — the webhook
        // remains the authoritative lifecycle source.
        try {
            $this->trackingProcessor->process($update);
        } catch (\Exception $reconcileFailure) {
            unset($reconcileFailure);
        }

        return $this->statusResult($trackingNumber, $carrierTitle, $update);
    }

    private function statusResult(string $trackingNumber, string $carrierTitle, TrackingUpdateInterface $update): Result
    {
        $status = new Status();
        $status->setCarrier('secomm_ghn');
        $status->setCarrierTitle($carrierTitle);
        $status->setTracking($trackingNumber);
        $status->setStatus((string) $this->statusDisplay($update));
        $status->setTrackSummary((string) $this->statusDisplay($update));

        $expected = $this->expectedDeliveryDate($update);
        if ($expected !== null) {
            $status->setDeliverydate($expected['date']);
            $status->setDeliverytime($expected['time']);
        }

        $progress = $this->progressDetail($update);
        if ($progress !== []) {
            $status->setProgressdetail($progress);
        }

        $result = new Result();
        $result->append($status);

        return $result;
    }

    private function errorResult(string $trackingNumber, string $carrierTitle, ?GhnApiException $failure): Result
    {
        $message = $failure === null
            ? 'not found at provider'
            : (string) $failure->getMessage();

        // Magento's Error template renders its own generic "unavailable" text — the provider
        // detail above stays in the log only (never rendered, never PII-leaking).
        $error = new Error();
        $error->setCarrier('secomm_ghn');
        $error->setCarrierTitle($carrierTitle);
        $error->setTracking($trackingNumber);
        $error->setErrorMessage($message);

        $result = new Result();
        $result->append($error);

        return $result;
    }

    /**
     * "Delivered (GHN status: delivered)" — normalized label first, raw provider status second.
     */
    private function statusDisplay(TrackingUpdateInterface $update): Phrase
    {
        return __('%1 (GHN status: %2)', $this->label($update->getNormalizedStatus()), $update->getCarrierStatusCode() ?? '');
    }

    private function label(string $normalizedStatus): Phrase
    {
        return __(self::LABELS[$normalizedStatus] ?? self::LABELS[NormalizedTrackingStatus::UNKNOWN]);
    }

    /**
     * @return array{date: string, time: string}|null
     */
    private function expectedDeliveryDate(TrackingUpdateInterface $update): ?array
    {
        $expected = trim((string) ($update->getRaw()['expected_delivery_time'] ?? ''));
        if ($expected === '') {
            return null;
        }

        $timestamp = ctype_digit($expected) ? (int) $expected : strtotime($expected);
        if ($timestamp === false || $timestamp <= 0) {
            return null;
        }

        return ['date' => date('Y-m-d', $timestamp), 'time' => date('H:i:s', $timestamp)];
    }

    /**
     * Curated activity feed — status label + parsed time only (brief §7: no raw payload).
     *
     * @return list<array{deliverydate: string, deliverytime: string, activity: string}>
     */
    private function progressDetail(TrackingUpdateInterface $update): array
    {
        $progress = [];
        foreach ($update->getRaw()['log'] ?? [] as $entry) {
            $timestamp = $this->parseLogTime((string) ($entry['time'] ?? ''));
            $progress[] = [
                'deliverydate' => $timestamp !== null ? date('Y-m-d', $timestamp) : '',
                'deliverytime' => $timestamp !== null ? date('H:i:s', $timestamp) : '',
                'activity' => (string) __('%1 (GHN: %2)', $this->label($this->statusOf($entry)), (string) ($entry['status'] ?? '')),
            ];
        }

        return $progress;
    }

    private function statusOf(array $entry): string
    {
        $raw = trim((string) ($entry['status'] ?? ''));
        if ($raw === '') {
            return NormalizedTrackingStatus::UNKNOWN;
        }

        // Reuse the ONE mapper for the activity labels too — no second mapping table anywhere.
        // GhnStatusMapper::map() never throws (unknown values map to UNKNOWN).
        return $this->statusMapper->map($raw);
    }

    private function parseLogTime(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $timestamp = ctype_digit($value) ? (int) $value : strtotime($value);

        return $timestamp !== false && $timestamp > 0 ? $timestamp : null;
    }
}
