<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * TASK-WY6WP5 — Carrier options for the ADD COVERAGE flow: registered CARRIER targets
 * that do NOT yet have an explicit coverage config (directive §6 — one explicit config
 * per type+code; configured targets are excluded so a duplicate can never be picked from
 * the UI). Registered means only "supports coverage configuration" — the option list is
 * never persisted by itself. No manual code entry: the registry is the only source.
 */
class RegisteredCarrierOptions implements OptionSourceInterface
{
    private CoverageTargetRegistry $targetRegistry;

    private CarrierCoverageConfigAdapter $configAdapter;

    public function __construct(CoverageTargetRegistry $targetRegistry, CarrierCoverageConfigAdapter $configAdapter)
    {
        $this->targetRegistry = $targetRegistry;
        $this->configAdapter = $configAdapter;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->targetRegistry->getAllByType(CoverageTargetType::CARRIER) as $target) {
            $identity = $target->getIdentity();
            if ($this->configAdapter->hasExplicitConfig($identity)) {
                continue;
            }
            $options[] = ['value' => $identity->code(), 'label' => $target->getLabel()];
        }

        return $options;
    }
}
