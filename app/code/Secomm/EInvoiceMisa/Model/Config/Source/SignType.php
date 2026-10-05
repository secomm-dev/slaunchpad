<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SignType implements OptionSourceInterface
{
    /**
     * @return array<int, array<string, int|string>>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 1,
                'label' => __('1 - Issue invoice/ticket signed by USB or soft certificate'),
            ],
            [
                'value' => 2,
                'label' => __('2 - Issue invoice/ticket signed by HSM'),
            ],
            [
                'value' => 3,
                'label' => __('3 - Issue invoice/ticket asynchronously signed by HSM'),
            ],
            [
                'value' => 4,
                'label' => __('4 - Issue non-coded ticket without digital signature'),
            ],
            [
                'value' => 5,
                'label' => __('5 - Issue POS invoice without digital signature'),
            ],
        ];
    }
}
