<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Vendor;

use Secomm\Tracking\Model\Event\TrackingEvent;

/**
 * FEAT-31X6N2 — server-side vendor adapter contract (spec §6).
 * Adapters map canonical event names to vendor names; they receive ONLY hashed
 * user data (the DTO contract guarantees it) and never run in request scope.
 */
interface VendorAdapterInterface
{
    /**
     * Vendor key as stored in the outbox (`vendor` column): meta | tiktok.
     */
    public function getVendorKey(): string;

    /**
     * Whether this vendor is configured (enabled + credentials present).
     */
    public function isConfigured(?string $scopeCode = null): bool;

    /**
     * Map a canonical event to the vendor's event name; null when the vendor
     * does not carry this event (e.g. refund not enabled for the vendor).
     */
    public function mapEventName(string $eventName): ?string;

    /**
     * Send one event. Must throw VendorSendException on transport failure —
     * the flush service owns retry policy; adapters never retry themselves.
     */
    public function send(TrackingEvent $event, ?string $scopeCode = null): DeliveryResult;
}
