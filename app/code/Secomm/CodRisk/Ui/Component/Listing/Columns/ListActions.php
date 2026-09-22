<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Ui\Component\Listing\Columns;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions: Edit + Activate/Deactivate (deactivate preferred over delete —
 * audit trail, mockup Flow E).
 */
class ListActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
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

        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)($item['entity_id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $item[$this->getName()]['edit'] = [
                'href' => $this->urlBuilder->getUrl('codrisk/lists/edit', ['id' => $id]),
                'label' => (string)__('Edit'),
            ];

            if ((int)($item['is_active'] ?? 0) === 1) {
                $item[$this->getName()]['deactivate'] = [
                    'href' => $this->urlBuilder->getUrl('codrisk/lists/deactivate', ['id' => $id, 'active' => 0]),
                    'label' => (string)__('Deactivate'),
                    'confirm' => [
                        'title' => (string)__('Deactivate'),
                        'message' => (string)__('Deactivate this list record? The phone re-enters evaluation immediately.'),
                    ],
                ];
            } else {
                $item[$this->getName()]['activate'] = [
                    'href' => $this->urlBuilder->getUrl('codrisk/lists/deactivate', ['id' => $id, 'active' => 1]),
                    'label' => (string)__('Activate'),
                ];
            }
        }

        return $dataSource;
    }
}
