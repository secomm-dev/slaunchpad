<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Shipment;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — per-process guard for the offline RECORDING window on a
 * shipment. The commit_after recorder persists the fulfillment metadata + package facts by
 * RE-SAVING the shipment (already committed at that point), which synchronously re-fires both
 * shipment save events while the recording is still in flight:
 *
 * - the intent validator must stand down for such saves — they carry the request intent (the
 *   same HTTP request is still in scope) but are NOT a new operator offline action, and
 * - the recorder itself must not re-enter (its own persistence re-fires its event).
 *
 * Process-local static state, mirroring the in-flight guards already used by the carrier
 * create observers (GhnShipmentCreateObserver::$inFlight) — no cross-request meaning.
 */
final class OfflineRecordingState
{
    private static array $recordingShipmentIds = [];

    public static function begin(int $shipmentId): void
    {
        if ($shipmentId > 0) {
            self::$recordingShipmentIds[$shipmentId] = true;
        }
    }

    public static function end(int $shipmentId): void
    {
        unset(self::$recordingShipmentIds[$shipmentId]);
    }

    public static function isRecording(int $shipmentId): bool
    {
        return $shipmentId > 0 && isset(self::$recordingShipmentIds[$shipmentId]);
    }
}
