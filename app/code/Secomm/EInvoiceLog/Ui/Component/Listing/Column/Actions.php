<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions for issue log admin grid.
 */
class Actions extends Column
{
    private const URL_PATH_VIEW = 'secomm_einvoice_log/issuelog/view';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Add view action link to each grid row.
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }

            $item[$this->getData('name')] = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_VIEW,
                        ['entity_id' => (int) $item['entity_id']]
                    ),
                    'label' => __('View'),
                ],
            ];
        }

        return $dataSource;
    }
}
