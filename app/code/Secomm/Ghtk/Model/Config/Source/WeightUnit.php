<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WeightUnit implements OptionSourceInterface
{
    public const KILOGRAM = 'kg';
    public const GRAM = 'g';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::KILOGRAM, 'label' => __('Kilogram (kg)')],
            ['value' => self::GRAM, 'label' => __('Gram (g)')],
        ];
    }
}
