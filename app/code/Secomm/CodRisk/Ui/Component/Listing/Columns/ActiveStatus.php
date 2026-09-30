<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders is_active as a colored pill (● green Active / ● gray Inactive).
 * Column body template = ui/grid/cells/html; value is server-built HTML.
 */
class ActiveStatus extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
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

        foreach ($dataSource['data']['items'] as &$row) {
            $active = (int)($row['is_active'] ?? 0) === 1;
            $color = $active ? '#1e7e34' : '#575756';
            $background = $active ? '#e6f4ea' : '#eeeced';
            $label = $active ? __('Active') : __('Inactive');

            $row[$this->getName()] = sprintf(
                '<span style="display:inline-block;padding:2px 10px;border-radius:10px;font-weight:600;background:%s;color:%s;">● %s</span>',
                $background,
                $color,
                $label
            );
        }

        return $dataSource;
    }
}