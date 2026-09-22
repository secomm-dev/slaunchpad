<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — request-scoped outcome collector implementation.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;

/**
 * Shared-per-request in-memory buffer (no shared="false", no session, no DB, no cache — state
 * lives exactly as long as the PHP request and is bracketed per rate-collection execution).
 *
 * Isolation contract: beginCollection() discards leftovers, so two sequential collections in the
 * same HTTP request (multi-address checkout, address re-estimate) never see each other's
 * outcomes. Orphan record() calls (no open bracket) are dropped with a debug log — safe, never
 * thrown into the carrier path.
 */
class CarrierRateOutcomeCollector implements CarrierRateOutcomeCollectorInterface
{
    /** @var array<string, array<string, CarrierRateOutcomeInterface>> */
    private array $outcomes = [];

    private bool $active = false;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function beginCollection(): void
    {
        $this->outcomes = [];
        $this->active = true;
    }

    public function record(
        string $carrierCode,
        string $methodCode,
        CarrierRateOutcomeInterface $outcome
    ): void {
        if ($carrierCode === '' || $methodCode === '') {
            throw new \InvalidArgumentException(
                'Carrier outcome identity requires non-empty carrier_code and method_code.'
            );
        }

        if (!$this->active) {
            // Orphan report: carrier ran outside a bracketed collection. Drop silently for the
            // caller, keep a breadcrumb for diagnostics.
            $this->logger->debug(
                'ShippingCore outcome collector dropped orphan report (no open collection): '
                . '{carrier}/{method} {status}',
                ['carrier' => $carrierCode, 'method' => $methodCode, 'status' => $outcome->getStatus()]
            );

            return;
        }

        $this->outcomes[$carrierCode][$methodCode] = $outcome;
    }

    public function getOutcomes(): array
    {
        return $this->active ? $this->outcomes : [];
    }

    public function endCollection(): void
    {
        $this->outcomes = [];
        $this->active = false;
    }
}
