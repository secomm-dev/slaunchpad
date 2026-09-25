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
    /**
     * TASK-RT50KH fix — the join MUST be applied at setCollection time: the vendor's
     * `_prepareCollection()` sets the collection and then the parent chain
     * (Extended::_prepareCollection) applies the request sort AND LOADS — a join applied
     * after that runs too late (ORDER BY city_code on an unjoined select → SQLSTATE 1054 →
     * the grid loses every row).
     *
     * `setCollection` is a real method on Magento\Backend\Block\Widget\Grid (line 196) and
     * is invoked by the vendor's `_prepareCollection()` right after it builds the collection
     * — joining here means the sort and load always see the column.
     */
    public function setCollection($collection)
    {
        if ($collection instanceof \Magento\Framework\Data\Collection\AbstractDb) {
            $select = $collection->getSelect();
            // Join once — setCollection may be reached by more than one prepare pass.
            $from = $select->getPart(\Zend_Db_Select::FROM);
            if (!isset($from['launchpad_rate_city'])) {
                $select->joinLeft(
                    ['launchpad_rate_city' => 'launchpad_mptablerate_rate_city'],
                    'main_table.rate_id = launchpad_rate_city.rate_id',
                    []
                )->joinLeft(
                    ['launchpad_city_master' => 'directory_region_city'],
                    'launchpad_city_master.code = launchpad_rate_city.city_code',
                    [
                        // Raw code — required by the CSV export column (import roundtrip).
                        'city_code' => 'launchpad_rate_city.city_code',
                        // Display: resolved City / Area name; falls back to the raw code when
                        // the code no longer resolves (stale constraint stays visible).
                        'city_area_display' => new \Zend_Db_Expr(
                            'COALESCE(launchpad_city_master.default_name, launchpad_rate_city.city_code)'
                        ),
                    ]
                );
            }
        }

        return parent::setCollection($collection);
    }

    protected function _prepareColumns()
    {
        parent::_prepareColumns();

        // TASK-RT50KH — add the City / Area column and position it after State / Region.
        $isCsvExport = $this->_request->getFullActionName() === 'mptablerate_method_rateExportCsv';
        if ($isCsvExport) {
            // Export keeps the machine contract: header + values are the raw `city_code`
            // (the importer resolves columns by header name and validates codes).
            $this->addColumn('city_code', [
                'header' => 'city_code',
                'index' => 'city_code',
                'type' => 'text',
                'default' => '',
            ]);
        } else {
            // On-screen grid shows the resolved City / Area NAME (fallback = raw code for
            // stale constraints), positioned right after State / Region.
            $this->addColumn('city_area_display', [
                'header' => __('City / Area'),
                'index' => 'city_area_display',
                'type' => 'text',
                'default' => '',
            ]);
        }

        // TASK-RT50KH — position City / Area right after State / Region using the layout's
        // reorderChild (direct, no dependency on sortColumnsByOrder timing).
        if ($this->getColumn('city_area_display') && $this->getColumn('region')) {
            $this->getLayout()->reorderChild(
                $this->getColumnSet()->getNameInLayout(),
                $this->getColumn('city_area_display')->getNameInLayout(),
                $this->getColumn('region')->getNameInLayout()
            );
        }

        return $this;
    }
}
