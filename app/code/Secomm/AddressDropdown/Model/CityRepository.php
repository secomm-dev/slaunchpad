<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\AddressDropdown\Model;

use Secomm\AddressDropdown\Api\CityRepositoryInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityResource;
use Magento\Framework\Exception\NoSuchEntityException;

class CityRepository implements CityRepositoryInterface
{
    public function __construct(
        protected CityModelFactory $cityModelFactory,
        protected CityResource $cityResource

    ) {

    }

    /**
     * {@inheritdoc}
     */
    public function getById($cityId)
    {
        try {
            $city = $this->cityModelFactory->create();
            $this->cityResource->load($city, $cityId);
            if (!$city->getId()) {
                throw new NoSuchEntityException(__('City with id "%1" does not exist.', $cityId));
            }
            return $city;
        }catch (\Exception $exception){
            return null;
        }
    }
}
