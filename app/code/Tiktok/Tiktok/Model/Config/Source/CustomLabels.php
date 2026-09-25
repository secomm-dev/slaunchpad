<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Returns options for the custom labels
 */
class CustomLabels implements OptionSourceInterface
{
    /**
     * Return options array for custom labels.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'custom_label_1', 'label' => __('Custom Label 1')],
            ['value' => 'custom_label_2', 'label' => __('Custom Label 2')],
            ['value' => 'custom_label_3', 'label' => __('Custom Label 3')],
            ['value' => 'custom_label_4', 'label' => __('Custom Label 4')],
            ['value' => 'custom_label_5', 'label' => __('Custom Label 5')]
        ];
    }
}
