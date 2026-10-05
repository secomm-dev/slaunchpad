<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\EInvoiceLog\Model\IssueLog;

class ReferenceType implements OptionSourceInterface
{
    /**
     * @return array<int, array<string, int|string>>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (IssueLog::getReferenceTypeLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
