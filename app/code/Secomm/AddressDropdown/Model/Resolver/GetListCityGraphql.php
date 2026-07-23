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
use Secomm\AddressDropdown\Model\DataStorage;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory as CityCollectionFactory;

class GetListCityGraphql implements ResolverInterface
{

    public function __construct(
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
            $cityCollection = $this->cityCollectionFactory->create();
            $cityCollection->addFieldToSelect('*');
            if (isset($args['input']['region_id'])) {
                $cityCollection->addFieldToFilter('region_id', $args['input']['region_id']);
            }
            $cityCollection->load();
            foreach ($cityCollection as $city) {
                $output[] = [
                    'city_id' => $city->getCityId(),
                    'region_id' => $city->getRegionId(),
                    'label' => $city->getName() ?? $city->getDefaultName(),
                    'default_name' => $city->getDefaultName(),
                ];
            }
            return $output;
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }
    }
}
