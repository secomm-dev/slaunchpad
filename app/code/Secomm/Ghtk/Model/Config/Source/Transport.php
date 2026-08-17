<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Transport implements OptionSourceInterface
{
    public const ROAD = 'road';
    public const FLY = 'fly';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::ROAD, 'label' => __('Road')],
            ['value' => self::FLY, 'label' => __('Fly')],
        ];
    }
}
