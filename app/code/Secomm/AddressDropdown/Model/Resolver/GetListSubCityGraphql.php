<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Secomm\AddressDropdown\Model\ResourceModel\SubCityModel\SubCityLocaleCollectionFactory as SubCityCollectionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;
use Secomm\AddressDropdown\Model\DataStorage;

class GetListSubCityGraphql implements ResolverInterface
{

    public function __construct(
        protected SubCityCollectionFactory $subCityCollectionFactory,
        protected CityCollectionFactory $cityCollectionFactory,
        protected DataStorage $dataStorage
    )
    {
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlNoSuchEntityException
     */
    public function resolve(
        Field       $field,
                    $context,
        ResolveInfo $info,
        array       $value = null,
        array       $args = null)
    {
        try {
            $output = [];
            if (isset($args['input']['area']) && $args['input']['area'] === \Magento\Framework\App\Area::AREA_ADMINHTML) {
                $this->dataStorage->set('area', $args['input']['area']);
            }
            $subCityCollectionFactory = $this->subCityCollectionFactory->create();
            $subCityCollectionFactory->addFieldToSelect('*');
            if (isset($args['input']['default_name'])) {
                $cityCollectionFactory = $this->cityCollectionFactory->create();
                $cityCollectionFactory->addFieldToSelect('*');
                $cityCollectionFactory->addFieldToFilter('default_name', $args['input']['default_name']);

                if (isset($args['input']['region_id'])) {
                    $cityCollectionFactory->addFieldToFilter('region_id', $args['input']['region_id']);
                }
                $cityCollectionFactory->load();
                $city = $cityCollectionFactory->getFirstItem();
                $subCityCollectionFactory->addFieldToFilter('city_id', $city->getCityId());
            }
            $subCityCollectionFactory->load();
            foreach ($subCityCollectionFactory as $subCity) {
                $output[] = [
                    'sub_city_id' => $subCity->getDefaultName(),
                    'city_id' => $subCity->getCityId(),
                    'default_name' => $subCity->getDefaultName(),
                    'label' => $subCity->getName() ?? $subCity->getDefaultName(),
                ];
            }
            return $output;
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }
    }
}
