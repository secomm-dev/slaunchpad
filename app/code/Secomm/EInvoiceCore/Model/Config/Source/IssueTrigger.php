<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\EInvoiceCore\Model\Config;

/**
 * Issue trigger source options for admin configuration.
 */
class IssueTrigger implements OptionSourceInterface
{
    /**
     * Return admin configuration options.
     *
     * @return array<int, array<string, string|\Magento\Framework\Phrase>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::TRIGGER_MANUAL, 'label' => __('Manual (Admin only)')],
            ['value' => Config::TRIGGER_ON_SHIPMENT, 'label' => __('First Shipment Created')],
        ];
    }
}
