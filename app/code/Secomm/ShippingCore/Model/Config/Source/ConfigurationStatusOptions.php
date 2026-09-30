<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * TASK-WY6WP5 — Configuration Status options for the Shipping Coverage grid. "Configured"
 * = an explicit coverage config exists for the target (any supported scope); "Not
 * Configured" = the target runs on the documented runtime defaults.
 */
class ConfigurationStatusOptions implements OptionSourceInterface
{
    public const CONFIGURED = 'configured';

    public const NOT_CONFIGURED = 'not_configured';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CONFIGURED, 'label' => 'Configured'],
            ['value' => self::NOT_CONFIGURED, 'label' => 'Not Configured'],
        ];
    }
}
