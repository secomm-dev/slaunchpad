<?php

namespace Secomm\Ahamove\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class OrderStatusOption implements OptionSourceInterface
{
    public function __construct(
        CollectionFactory $statusCollectionFactory
    )
    {
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        $optionBlank[] = ['value' => '', 'label' => __('---Please select---')];
        $options = $this->statusCollectionFactory->create()->toOptionArray();

        return array_merge($optionBlank, $options);
    }
}