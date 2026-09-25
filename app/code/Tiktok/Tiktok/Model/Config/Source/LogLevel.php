<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Logging level source model
 */
class LogLevel implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [['value' => 'info', 'label' => __('Info')], ['value' => 'debug', 'label' => __('Debug')]];
    }
}
