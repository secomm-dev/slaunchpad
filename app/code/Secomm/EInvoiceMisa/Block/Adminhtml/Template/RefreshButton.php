<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Block\Adminhtml\Template;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Toolbar button to refresh the cached MeInvoice template list.
 */
class RefreshButton implements ButtonProviderInterface
{
    /**
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritdoc
     *
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Refresh From MeInvoice'),
            'on_click' => sprintf(
                "location.href = '%s';",
                $this->urlBuilder->getUrl('secomm_einvoice_misa/template/refresh')
            ),
            'class' => 'primary',
            'sort_order' => 10,
        ];
    }
}
