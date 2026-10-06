<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\EInvoiceLog\Model\IssueLog;

/**
 * Status filter options for issue log admin grid.
 */
class Status implements OptionSourceInterface
{
    /**
     * Return log status options.
     *
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => IssueLog::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => IssueLog::STATUS_PROCESSING, 'label' => __('Processing')],
            ['value' => IssueLog::STATUS_SUCCESS, 'label' => __('Success')],
            ['value' => IssueLog::STATUS_FAILED, 'label' => __('Failed')],
            ['value' => IssueLog::STATUS_CANCELLED, 'label' => __('Cancelled')],
        ];
    }
}
