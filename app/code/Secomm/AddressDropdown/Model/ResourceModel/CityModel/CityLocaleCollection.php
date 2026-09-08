<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\ResourceModel\CityModel;

use Magento\Framework\App\State;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Model\CityModel;
use Secomm\AddressDropdown\Model\DataStorage;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource;
use Zend_Db_Expr;

class CityLocaleCollection extends CityCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_region_city_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(CityModel::class, CityResource::class);
    }

    public function __construct(
        EntityFactoryInterface      $entityFactory,
        LoggerInterface             $logger,
        FetchStrategyInterface      $fetchStrategy,
        ManagerInterface            $eventManager,
        protected ResolverInterface $localeResolver,
        protected DataStorage       $dataStorage,
        protected State             $appState,
        AdapterInterface            $connection = null,
        AbstractDb                  $resource = null
    )
    {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * Join with directory_region_city_name table
     *
     * @return $this|CityCollection|void
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        if ($this->dataStorage->get('area') !== \Magento\Framework\App\Area::AREA_ADMINHTML
        ) {
            $locale = $this->localeResolver->getLocale();
        } else {
            $locale = Resolver::DEFAULT_LOCALE;
        }

        $this->addBindParam(':region_locale', $locale);
        $this->getSelect()->joinLeft(
            ['rname' => $this->getTable('directory_region_city_name')],
            'main_table.city_id = rname.city_id AND rname.locale = :region_locale',
            ['name' => 'rname.name']
        );
        // Canonical generic sort (TASK-7HVGAB): effective localized display name with
        // default_name fallback, city_id as deterministic tie-breaker — independent of
        // insert order or execution plan. Language-agnostic: ordering quality is owned by
        // the column collation (schema baseline). Mirrors LocationHierarchyProvider.
        $this->getSelect()->order(new Zend_Db_Expr(
            'COALESCE(rname.name, main_table.default_name) ASC, main_table.city_id ASC'
        ));

        return $this;
    }
}
