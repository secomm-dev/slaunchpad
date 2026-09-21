<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — Mageplaza rate grid + City/Area column (grid + CSV export).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Block\Adminhtml\Method\Edit\Tab\Rate;

use Mageplaza\TableRateShipping\Block\Adminhtml\Method\Edit\Tab\Rate\Grid as MageplazaRateGrid;

/**
 * Preference subclass: LEFT JOINs the city constraint table into the grid collection and adds
 * the `City / Area` column. Because the export renders through the same grid columns, the CSV
 * export gains the stable `city_code` column for round-trip importing automatically.
 */
class CityGrid extends MageplazaRateGrid
{
    protected function _prepareCollection()
    {
        parent::_prepareCollection();

        $collection = $this->getCollection();
        if ($collection !== null) {
            $collection->getSelect()->joinLeft(
                ['launchpad_rate_city' => 'launchpad_mptablerate_rate_city'],
                'main_table.rate_id = launchpad_rate_city.rate_id',
                ['city_code' => 'launchpad_rate_city.city_code']
            );
            $this->setCollection($collection);
        }

        return $this;
    }

    protected function _prepareColumns()
    {
        parent::_prepareColumns();

        $this->addColumn('city_code', [
            'header' => __('City / Area'),
            'index' => 'city_code',
            'type' => 'text',
            'default' => '',
        ]);

        return $this;
    }
}
