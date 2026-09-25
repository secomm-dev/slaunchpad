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
 * Standard collection factory for {@see CityLocaleCollection}.
 *
 * TASK-SEC-BLOCKER: the class went missing in the "refactor AddressDropdown…" commit while
 * GetListCityGraphql, CustomerData\CityData, Customer\Address\Config\Selector\City and the
 * ValidateVietNamWard plugin suites still constructor-inject it — a missing definition means
 * ObjectManager cannot instantiate those classes at runtime (GraphQL GetListCity fatal).
 */
class CityLocaleCollectionFactory
{
    private ObjectManagerInterface $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param array $data
     * @return CityLocaleCollection
     */
    public function create(array $data = []): CityLocaleCollection
    {
        return $this->objectManager->create(CityLocaleCollection::class, $data);
    }
}
