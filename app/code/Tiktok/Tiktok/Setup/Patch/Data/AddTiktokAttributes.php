<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Add tiktok attributes to product entity
 */
class AddTiktokAttributes implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * AddTiktokAttributes constructor
     *
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     * @param \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Add 'tiktok_brand' attribute
        $eavSetup->addAttribute(Product::ENTITY, 'tiktok_brand', [
            'type' => 'varchar',
            'label' => 'TikTok Brand',
            'input' => 'text',
            'required' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'group' => 'General'
        ]);

        // Add 'tiktok_condition' attribute
        $eavSetup->addAttribute(Product::ENTITY, 'tiktok_condition', [
            'type' => 'varchar',
            'label' => 'TikTok Condition',
            'input' => 'text',
            'required' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'group' => 'General'
        ]);

        // Add 'tiktok_product_group_id' attribute
        $eavSetup->addAttribute(Product::ENTITY, 'tiktok_product_group_id', [
            'type' => 'varchar',
            'label' => 'TikTok Product Group',
            'input' => 'text',
            'required' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'group' => 'General'
        ]);
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, 'tiktok_brand');
        $eavSetup->removeAttribute(Product::ENTITY, 'tiktok_condition');
        $eavSetup->removeAttribute(Product::ENTITY, 'tiktok_product_group_id');
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
