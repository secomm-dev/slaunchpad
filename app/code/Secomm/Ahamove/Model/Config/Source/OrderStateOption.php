<?php
/**
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\Config\Source\Order\Status;

class OrderStateOption implements OptionSourceInterface
{
    public function __construct(
        Status $orderStatusSource
    )
    {
        $this->orderStatusSource = $orderStatusSource;
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $optionBlank[] = ['value' => '', 'label' => __('---Please select---')];
        $options = $this->orderStatusSource->toOptionArray();

        return array_merge($optionBlank, $options);
    }
}
