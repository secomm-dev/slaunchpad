<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Block\Adminhtml\Mapping\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Framework\UrlInterface;

class BackButton implements ButtonProviderInterface
{
    public function __construct(
        protected UrlInterface $urlBuilder
    ) {
    }

    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->urlBuilder->getUrl('*/*/')),
            'class' => 'back',
            'sort_order' => 10
        ];
    }
}
