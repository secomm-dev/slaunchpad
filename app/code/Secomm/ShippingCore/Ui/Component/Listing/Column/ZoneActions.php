<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — grid row actions (edit / delete).
 */
class ZoneActions extends Column
{
    private const URL_PATH_EDIT = 'secomm_shippingcore/zone/edit';

    private const URL_PATH_DELETE = 'secomm_shippingcore/zone/delete';

    private UrlInterface $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $zoneId = $item['zone_id'] ?? null;
            if ($zoneId === null) {
                continue;
            }
            $item[$this->getName()]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['zone_id' => $zoneId]),
                'label' => __('Edit'),
            ];
            $item[$this->getName()]['delete'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_DELETE, ['zone_id' => $zoneId]),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete zone "%1"', $item['code'] ?? $zoneId),
                    'message' => __(
                        'Delete this zone? Deletion is BLOCKED while a carrier still references it — remove the reference in Secomm → Shipping Coverage first.'
                    ),
                ],
            ];
        }

        return $dataSource;
    }
}
