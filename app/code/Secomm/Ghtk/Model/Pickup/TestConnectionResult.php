<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Pickup;

/**
 * TASK-3HPB76 — typed Test Connection result for admin/ops (§8): never a bare
 * bool. Statuses separate API connectivity/auth from the configured pickup-id
 * validation, so "no pickup ID configured" is NOT a failure and "configured ID
 * missing from the merchant list" fails loudly (§11/§13 — no first-pickup
 * fallback).
 */
final class TestConnectionResult
{
    public const CONNECTED = 'CONNECTED';
    public const AUTH_FAILED = 'AUTH_FAILED';
    public const TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';
    public const PICKUP_ID_INVALID = 'PICKUP_ID_INVALID';

    private function __construct(
        private readonly string $status,
        private readonly string $message,
        private readonly ?string $configuredPickupId = null,
        private readonly ?string $configuredPickupName = null,
        private readonly int $pickupCount = 0
    ) {
    }

    public static function connected(string $message, int $pickupCount, ?string $configuredPickupId = null, ?string $configuredPickupName = null): self
    {
        return new self(self::CONNECTED, $message, $configuredPickupId, $configuredPickupName, $pickupCount);
    }

    public static function authFailed(string $message, ?string $configuredPickupId = null): self
    {
        return new self(self::AUTH_FAILED, $message, $configuredPickupId);
    }

    public static function technicalFailure(string $message, ?string $configuredPickupId = null): self
    {
        return new self(self::TECHNICAL_FAILURE, $message, $configuredPickupId);
    }

    public static function pickupIdInvalid(string $message, string $configuredPickupId, int $pickupCount): self
    {
        return new self(self::PICKUP_ID_INVALID, $message, $configuredPickupId, null, $pickupCount);
    }

    /** CONNECTED | AUTH_FAILED | TECHNICAL_FAILURE | PICKUP_ID_INVALID */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** Admin-safe message (no token/raw payload). */
    public function getMessage(): string
    {
        return $this->message;
    }

    public function getConfiguredPickupId(): ?string
    {
        return $this->configuredPickupId;
    }

    /** Name of the configured pickup (diagnostics when the configured ID matched). */
    public function getConfiguredPickupName(): ?string
    {
        return $this->configuredPickupName;
    }

    public function getPickupCount(): int
    {
        return $this->pickupCount;
    }

    /** True for the two successful connectivity statuses (§11 — no-id is still CONNECTED). */
    public function isConnectionOk(): bool
    {
        return $this->status === self::CONNECTED;
    }
}
