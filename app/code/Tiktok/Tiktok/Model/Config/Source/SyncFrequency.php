<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Returns options for the sync_frequency field
 */
class SyncFrequency implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '0 * * * *', 'label' => __('Hourly')],
            ['value' => '0 0 * * *', 'label' => __('Daily')],
            ['value' => '0 0 * * 0', 'label' => __('Weekly')],];
    }
}
