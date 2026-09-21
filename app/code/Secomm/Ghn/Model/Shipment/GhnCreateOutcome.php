<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

/**
 * TASK-9Q5ZAK (GHN-D) — normalized CREATE outcome (mirror of the E-C1 rate outcome semantics,
 * scoped to the GHN module until a ShippingCore shipment-outcome contract exists — §52.4).
 *
 * Status decides orchestration; the reason is diagnostic metadata only. SUCCESS carries the
 * provider order code (the tracking number) and never a failure reason; non-SUCCESS never
 * carries an order code — an uncertain result (timeout / 5xx / malformed after the POST) is
 * TECHNICAL_FAILURE with UNKNOWN persistence state and is reconciled by resubmitting the same
 * client_order_code, never by blind re-creation.
 */
final class GhnCreateOutcome
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_UNAVAILABLE = 'UNAVAILABLE';
    public const STATUS_TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';

    /**
     * @param string|null $orderCode provider order code (= tracking number), SUCCESS only
     * @param float|null $totalFee provider total fee (VND), SUCCESS only
     */
    private function __construct(
        private readonly string $status,
        private readonly ?string $reason,
        private readonly string $clientOrderCode,
        private readonly ?string $orderCode = null,
        private readonly ?float $totalFee = null,
        private readonly ?string $expectedDeliveryAt = null
    ) {
    }

    public static function success(
        string $clientOrderCode,
        string $orderCode,
        ?float $totalFee,
        ?string $expectedDeliveryAt
    ): self {
        return new self(
            self::STATUS_SUCCESS,
            null,
            $clientOrderCode,
            $orderCode,
            $totalFee,
            $expectedDeliveryAt
        );
    }

    public static function unavailable(string $reason, string $clientOrderCode): self
    {
        return new self(self::STATUS_UNAVAILABLE, $reason, $clientOrderCode);
    }

    public static function technicalFailure(string $reason, string $clientOrderCode): self
    {
        return new self(self::STATUS_TECHNICAL_FAILURE, $reason, $clientOrderCode);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getClientOrderCode(): string
    {
        return $this->clientOrderCode;
    }

    public function getOrderCode(): ?string
    {
        return $this->orderCode;
    }

    public function getTotalFee(): ?float
    {
        return $this->totalFee;
    }

    public function getExpectedDeliveryAt(): ?string
    {
        return $this->expectedDeliveryAt;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
