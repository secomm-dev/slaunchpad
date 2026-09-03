<?php
/**
 * Copyright © Secomm DevTeam All rights reserved.
 * See COPYING.txt for license details.
 *
 * TASK-6MKF0V: removes the customer address EAV attribute `sub_city` that
 * AddNewAddressAttributeSubCityCustomer created. The original patch file was deleted as
 * part of the sub_city retirement; because its apply() is already recorded in patch_list,
 * removal is done through this NEW patch (applied data patches are never edited).
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Setup\Patch\Data;

use Magento\Customer\Model\Indexer\Address\AttributeProvider;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class RemoveSubCityCustomerAttribute implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attribute = $customerSetup->getEavConfig()->getAttribute(
            AttributeProvider::ENTITY,
            'sub_city'
        );
        if ($attribute && $attribute->getId()) {
            // Drop the stored values first so no orphaned rows survive the attribute delete.
            $this->moduleDataSetup->getConnection()->delete(
                $this->moduleDataSetup->getTable('customer_address_entity_varchar'),
                ['attribute_id = ?' => (int) $attribute->getId()]
            );
            $customerSetup->removeAttribute(AttributeProvider::ENTITY, 'sub_city');
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Re-add the attribute (kept for symmetry with the retired creator patch).
     *
     * {@inheritdoc}
     */
    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $customerSetup->addAttribute(AttributeProvider::ENTITY, 'sub_city', [
            'type' => 'varchar',
            'label' => 'Sub City',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'position' => 100,
            'system' => false,
            'backend' => ''
        ]);

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
}
