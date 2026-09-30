<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Model\ResourceModel\CityModel;

use Magento\Framework\ObjectManagerInterface;

/**
 * Standard collection factory for {@see CityCollection}.
 *
 * TASK-SEC-BLOCKER: restored after the "refactor AddressDropdown…" commit removed the
 * factory classes while production code and test suites still constructor-inject them.
 */
class CityCollectionFactory
{
    private ObjectManagerInterface $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param array $data
     * @return CityCollection
     */
    public function create(array $data = []): CityCollection
    {
        return $this->objectManager->create(CityCollection::class, $data);
    }
}
