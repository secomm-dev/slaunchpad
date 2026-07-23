<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Integration\Helper;


use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Helper\Data;

class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->dataHelper = $objectManager->get(Data::class);
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
    }

    public function testGetConfigValue()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $field = 'address/general/enable';

        $result = $this->dataHelper->getConfigValue($field, $storeId);

        $this->assertNotNull($result);
    }

    public function testIsAddressDropdownModuleEnabled()
    {
        $storeId = $this->storeManager->getStore()->getId();

        $result = $this->dataHelper->isAddressDropdownModuleEnabled($storeId);

        $this->assertIsBool($result);
    }
}
