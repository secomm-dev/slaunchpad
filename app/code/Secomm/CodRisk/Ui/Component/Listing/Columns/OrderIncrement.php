<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Ui\Component\Listing\Columns;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders order entity_id as the human increment id (#000000036) instead of the
 * raw internal id. Resolves increments in one batched query per rendered page.
 */
class OrderIncrement extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int)($row['order_id'] ?? 0),
            $dataSource['data']['items']
        ))));

        $map = [];
        if ($ids !== []) {
            $criteria = $this->searchCriteriaBuilder->addFilter('entity_id', $ids, 'in')->create();
            foreach ($this->orderRepository->getList($criteria)->getItems() as $order) {
                $map[(int)$order->getEntityId()] = (string)$order->getIncrementId();
            }
        }

        foreach ($dataSource['data']['items'] as &$row) {
            $orderId = (int)($row['order_id'] ?? 0);
            $row[$this->getName()] = $orderId === 0
                ? '—'
                : '#' . ($map[$orderId] ?? (string)$orderId);
        }

        return $dataSource;
    }
}