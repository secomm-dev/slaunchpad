<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\AddressDropdown\Api\Data\CityInterface;

interface CityRepositoryInterface
{
    /**
     * Get city by ID.
     *
     * @param int $cityId
     * @return CityInterface
     * @throws NoSuchEntityException
     */
    public function getById($cityId);
}
