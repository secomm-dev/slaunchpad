<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\Pancake\Model\Mapping\PancakeStatusCatalog;

/**
 * Admin select of known Pancake order status codes (static catalog, not live API).
 */
class PancakeStatuses implements OptionSourceInterface
{
    public function __construct(
        private readonly PancakeStatusCatalog $catalog
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->catalog->getOptions() as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => sprintf('%s — %s', $code, $label),
            ];
        }

        return $options;
    }
}
