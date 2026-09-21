<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-WAWNDS — GHN carrier rate adjustment: providerRate → adjustment → finalRate, applied
 * ONLY to a SUCCESSFUL rate (never to availability/eligibility — an invalid parcel cannot be
 * made valid by any buffer). GHN-specific by ownership; ShippingCore orchestration untouched.
 *
 * Boundary note for the Launchpad/ShippingCore stream: `carrier_and_fallback` exists in the
 * config so merchants can express intent, but Secomm_Ghn can only ever adjust the GHN carrier
 * rate itself — the fallback rate is produced by the bridge composition, which must read the
 * same config if it wants to mirror the adjustment (never branded under GHN for a hard GHN
 * capability rejection).
 */
class GhnRateAdjuster
{
    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';

    public function __construct(
        private readonly Config $config,
        private readonly GhnLogger $logger
    ) {
    }

    public function adjust(float $providerRate, ?int $storeId = null): float
    {
        if (!$this->config->isRateAdjustmentEnabled($storeId)) {
            return $providerRate;
        }

        $value = $this->config->getRateAdjustmentValue($storeId);
        if ($value === 0.0) {
            return $providerRate;
        }

        $adjusted = $this->config->getRateAdjustmentType($storeId) === self::TYPE_PERCENT
            ? $providerRate * (1 + $value / 100)
            : $providerRate + $value;

        $final = $this->round((float) $adjusted, $this->config->getRateAdjustmentRounding($storeId));
        // providerRate and finalRate stay separately observable (brief §29/§36) — 0 PII.
        $this->logger->call('GHN rate adjustment applied', [
            'provider_rate' => $providerRate,
            'adjustment_type' => $this->config->getRateAdjustmentType($storeId),
            'adjustment_value' => $value,
            'final_rate' => $final,
        ]);

        return $final;
    }

    private function round(float $amount, string $rounding): float
    {
        if (in_array($rounding, ['1000', '5000'], true)) {
            $step = (int) $rounding;

            return (float) (ceil($amount / $step) * $step);
        }

        return round($amount, 2);
    }
}
