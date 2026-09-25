<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Standard model factory for {@see CityModel}.
 *
 * TASK-SEC-BLOCKER: restored after the "refactor AddressDropdown…" commit removed the model
 * factories while Helper\Data and test suites still inject them.
 */
class CityModelFactory
{
    private ObjectManagerInterface $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param array $data
     * @return CityModel
     */
    public function create(array $data = []): CityModel
    {
        return $this->objectManager->create(CityModel::class, $data);
    }
}
