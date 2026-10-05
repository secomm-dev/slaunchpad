<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CurrencyPrecision\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Auto + fixed decimal-precision options (ticket "Format Price Product").
 */
class PrecisionOptions implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [['label' => __('Auto (locale default)'), 'value' => 'auto']];
        foreach (range(0, 4) as $precision) {
            $options[] = ['label' => (string)$precision, 'value' => (string)$precision];
        }

        return $options;
    }
}
