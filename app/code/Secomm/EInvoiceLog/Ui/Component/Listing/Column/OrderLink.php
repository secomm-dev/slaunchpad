<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders order increment ID as a link to the sales order view page.
 */
class OrderLink extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Add order view URL to each grid row.
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $columnName = (string) $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (empty($item['order_id']) || empty($item['order_increment_id'])) {
                continue;
            }

            $item[$columnName] = sprintf(
                '<a href="%s">%s</a>',
                $this->escaper->escapeUrl(
                    $this->urlBuilder->getUrl('sales/order/view', ['order_id' => (int) $item['order_id']])
                ),
                $this->escaper->escapeHtml((string) $item['order_increment_id'])
            );
        }

        return $dataSource;
    }
}
