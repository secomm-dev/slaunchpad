<?php
/**
 * Copyright © Secomm All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\Base\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * TASK-RT50KH — upgrade the product dimension attributes to the P1 SHIPPING contract
 * (DEC-TASKRT50KH-001, TL-approved Option A "reuse via delete + recreate").
 *
 * The predecessor patch (AddDimensionProductAttribute) created `length`/`width`/`height` as
 * VARCHAR / STORE-scoped merchandising display attributes with no unit contract and no
 * validation. The P1 contract re-uses the SAME codes with authoritative semantics:
 * decimal backend, GLOBAL scope, "(cm)" labels, `validate-number validate-zero-or-greater`
 * admin validation, merchandising flags off.
 *
 * DATA-SAFETY NOTE (audited 2026-09-23 before writing this patch): the varchar value tables
 * carried ZERO rows for all three attributes across all 176 products — there is no display
 * data to preserve or migrate, so delete + recreate loses nothing. If a different environment
 * has accumulated values, RE-AUDIT before applying (the values are dropped with the varchar
 * attribute — they are NOT converted).
 *
 * Mechanics: `EavSetup::updateAttribute` cannot change the backend_type storage table, so the
 * upgrade is removeAttribute (the `eav_entity_attribute` FK cascade cleans the attribute-set
 * assignments) followed by addAttribute with 'group' => 'General', which re-propagates the
 * new attribute to ALL attribute sets automatically.
 */
class UpgradeDimensionAttributesToShippingContract implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * Same codes as the predecessor patch — consumers read by code (Secomm_Base shipping
     * plugin, Secomm_Ahamove volumetric, Secomm_Ghn dimension pre-validation reader).
     */
    private const ATTRIBUTES = [
        'length' => [
            'sort_order' => '30',
            'label' => 'Shipping Length (cm)',
        ],
        'width' => [
            'sort_order' => '31',
            'label' => 'Shipping Width (cm)',
        ],
        'height' => [
            'sort_order' => '32',
            'label' => 'Shipping Height (cm)',
        ],
    ];

    private ModuleDataSetupInterface $moduleDataSetup;

    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        foreach (self::ATTRIBUTES as $code => $definition) {
            // Idempotent remove first (the varchar predecessor may or may not have run in
            // this environment); the eav_entity_attribute FK cascade cleans set assignments.
            $eavSetup->removeAttribute(Product::ENTITY, $code);
            $eavSetup->addAttribute(Product::ENTITY, $code, $this->definition($definition));
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        foreach (array_keys(self::ATTRIBUTES) as $code) {
            // The decimal shipping attributes are removed; the predecessor's varchar
            // definition is NOT resurrected (its own revert then no-ops safely).
            $eavSetup->removeAttribute(Product::ENTITY, $code);
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public function getAliases()
    {
        return [];
    }

    public static function getDependencies()
    {
        // Contractual ordering on fresh installs: the predecessor creates the codes first,
        // this patch upgrades them. (Glob order would also resolve correctly — the
        // dependency makes it explicit.)
        return [AddDimensionProductAttribute::class];
    }

    private function definition(array $override): array
    {
        return array_merge([
            'type' => 'decimal',
            'label' => '',
            'input' => 'text',
            'frontend_class' => 'validate-number validate-zero-or-greater',
            'required' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'user_defined' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'unique' => false,
            'apply_to' => '',
            'group' => 'General',
            'used_in_product_listing' => false,
            'is_used_in_grid' => false,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => false,
        ], $override);
    }
}
