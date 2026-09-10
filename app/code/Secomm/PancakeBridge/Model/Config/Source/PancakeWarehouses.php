<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\PancakeFunction\Model\Warehouse\PosWarehouseCatalog;

class PancakeWarehouses implements OptionSourceInterface
{
    public function __construct(
        private readonly PosWarehouseCatalog $catalog
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->catalog->getOptions() as $id => $label) {
            $options[] = ['value' => $id, 'label' => $label];
        }
        if ($options === []) {
            $options[] = [
                'value' => '',
                'label' => (string) __('No warehouses (check Pancake API config)'),
            ];
        }
        return $options;
    }
}
