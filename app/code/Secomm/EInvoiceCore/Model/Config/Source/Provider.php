<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Electronic invoice provider options (extended by provider modules via di.xml).
 */
class Provider implements OptionSourceInterface
{
    /**
     * Provider source constructor.
     *
     * @param array $providers Map of provider code => label
     */
    public function __construct(
        private readonly array $providers = []
    ) {
    }

    /**
     * Return registered provider options.
     *
     * @return array<int, array<string, string|\Magento\Framework\Phrase>>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->providers as $code => $label) {
            $options[] = ['value' => $code, 'label' => __($label)];
        }

        return $options;
    }
}
