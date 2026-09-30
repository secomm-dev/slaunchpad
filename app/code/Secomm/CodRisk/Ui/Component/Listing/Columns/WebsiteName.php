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
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders website_id as the website name (0 = All Websites).
 */
class WebsiteName extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StoreManagerInterface $storeManager,
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

        $map = [0 => (string)__('All Websites')];
        foreach ($this->storeManager->getWebsites() as $website) {
            $map[(int)$website->getId()] = (string)$website->getName();
        }

        foreach ($dataSource['data']['items'] as &$row) {
            $websiteId = (int)($row['website_id'] ?? 0);
            $row[$this->getName()] = $map[$websiteId] ?? (string)$websiteId;
        }

        return $dataSource;
    }
}