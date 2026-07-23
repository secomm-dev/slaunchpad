<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\ResourceModel\SubCityModel;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityResource;
use Secomm\AddressDropdown\Model\SubCityModel;
use Secomm\AddressDropdown\Model\DataStorage;

class SubCityLocaleCollection extends SubCityCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'directory_city_sub_city_collection';

    public function __construct(
        EntityFactoryInterface      $entityFactory,
        LoggerInterface             $logger,
        FetchStrategyInterface      $fetchStrategy,
        ManagerInterface            $eventManager,
        protected ResolverInterface $localeResolver,
        protected DataStorage       $dataStorage,
        AdapterInterface            $connection = null,
        AbstractDb                  $resource = null
    )
    {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(SubCityModel::class, SubCityResource::class);
    }

    /**
     * Join with directory_city_sub_city_name table
     *
     * @return $this|SubCityCollection|void
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        if ($this->dataStorage->get('area') !== \Magento\Framework\App\Area::AREA_ADMINHTML) {
            $locale = $this->localeResolver->getLocale();
        } else {
            $locale = Resolver::DEFAULT_LOCALE;
        }

        $this->addBindParam(':region_locale', $locale);
        $this->getSelect()->joinLeft(
            ['rname' => $this->getTable('directory_city_sub_city_name')],
            'main_table.sub_city_id = rname.sub_city_id AND rname.locale = :region_locale',
            ['name' => 'rname.name']
        );

        return $this;
    }
}
