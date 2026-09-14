<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;

/**
 * Admin select options for NormalizedFulfillmentStatus (reusable by every POS UI).
 */
class NormalizedStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (NormalizedFulfillmentStatus::customerVisible() as $status) {
            $options[] = [
                'value' => $status,
                'label' => __(ucwords(str_replace('_', ' ', $status))),
            ];
        }
        $options[] = [
            'value' => NormalizedFulfillmentStatus::UNKNOWN,
            'label' => __('Unknown'),
        ];

        return $options;
    }
}
