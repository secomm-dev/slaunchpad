<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Reason;

use Secomm\CodRisk\Model\Config;

/**
 * Canonical COD risk reason catalog (spec nguồn §10.2, decisions D-02).
 *
 * The include flag is website-configurable via system config; its value is
 * snapshotted onto every recorded event so later config changes never
 * reinterpret history (spec nguồn §13).
 */
class ReasonCatalog
{
    /**
     * @return array<string, array{label: string, default_include: bool}>
     */
    public function getReasons(): array
    {
        return [
            'CUSTOMER_CANCELLATION_PRE_CONFIRM' => [
                'label' => 'Customer cancelled before confirmation',
                'default_include' => false,
            ],
            'CUSTOMER_REQUESTED_CANCEL' => [
                'label' => 'Customer asked admin/CS to cancel',
                'default_include' => false,
            ],
            'REFUSED_DELIVERY' => [
                'label' => 'Customer refused delivery',
                'default_include' => true,
            ],
            'UNREACHABLE_CUSTOMER' => [
                'label' => 'Customer unreachable during delivery',
                'default_include' => true,
            ],
            'OUT_OF_STOCK' => [
                'label' => 'Out of stock',
                'default_include' => false,
            ],
            'WRONG_PRICE' => [
                'label' => 'Wrong price',
                'default_include' => false,
            ],
            'DAMAGED_IN_TRANSIT' => [
                'label' => 'Damaged in transit',
                'default_include' => false,
            ],
            'LOST_SHIPMENT' => [
                'label' => 'Lost shipment',
                'default_include' => false,
            ],
            'SYSTEM_ERROR' => [
                'label' => 'System error',
                'default_include' => false,
            ],
            'UNDETERMINED' => [
                'label' => 'Undetermined cause',
                'default_include' => false,
            ],
        ];
    }

    public function getLabel(string $reasonCode): string
    {
        return $this->getReasons()[$reasonCode]['label'] ?? $reasonCode;
    }

    /**
     * Effective include flag at a given website — explicit config decision wins
     * (ON and OFF both), else the catalog default. This is the value snapshotted
     * onto events at record time.
     */
    public function isIncludedInCount(string $reasonCode, ?int $websiteId = null): bool
    {
        $reasons = $this->getReasons();
        if (!isset($reasons[$reasonCode])) {
            return false;
        }

        $stored = $this->config->getReasonIncludeFlag($reasonCode, $websiteId);

        return $stored ?? $reasons[$reasonCode]['default_include'];
    }

    public function __construct(
        private readonly Config $config,
    ) {
    }
}
