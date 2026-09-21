<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * SPEC-FEAT-FQWEQ3 §9 — environment switch. Selecting an environment switches the base URL
 * atomically (DEC-FEATYA2C0W-004 D3: an API profile is a complete capability bundle).
 */
class Environment implements OptionSourceInterface
{
    public const SANDBOX = 'sandbox';
    public const PRODUCTION = 'production';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::SANDBOX, 'label' => __('Sandbox')],
            ['value' => self::PRODUCTION, 'label' => __('Production')],
        ];
    }
}
