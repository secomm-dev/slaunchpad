<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * MeInvoice OptionUserDefined decimal digit options (0–4).
 */
class DecimalDigits implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        for ($i = 0; $i <= 4; $i++) {
            $options[] = ['value' => (string) $i, 'label' => (string) $i];
        }

        return $options;
    }
}
