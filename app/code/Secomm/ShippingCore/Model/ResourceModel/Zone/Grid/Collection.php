<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\ResourceModel\Zone\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — admin grid collection: adds the JSON_LENGTH code-list
 * counters as computed columns (MySQL 8 JSON functions; code lists are JSON columns).
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'secomm_shipping_zone',
        $resourceModel = \Secomm\ShippingCore\Model\ResourceModel\Zone::class
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    /**
     * @return SearchResult|Collection
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->addExpressionFieldToSelect(
            'province_count',
            'COALESCE(JSON_LENGTH({{include_province_codes}}), 0)',
            'include_province_codes'
        );
        $this->addExpressionFieldToSelect(
            'include_ward_count',
            'COALESCE(JSON_LENGTH({{include_ward_codes}}), 0)',
            'include_ward_codes'
        );

        return $this;
    }
}
