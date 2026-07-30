<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Seo\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Store\Model\StoreManagerInterface;

class DataPatch108 implements DataPatchInterface, PatchVersionInterface
{
    private $setup;

    private $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $setup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->setup = $setup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '1.0.8';
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->setup->startSetup();
        $setup = $this->setup;

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        if (!$eavSetup->getAttributeId(Product::ENTITY, 'm_seo_canonical')) {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'm_seo_canonical',
                [
                    'type'                    => 'text',
                    'backend'                 => '',
                    'frontend'                => '',
                    'label'                   => 'Canonical URL',
                    'input'                   => 'text',
                    'class'                   => '',
                    'source'                  => '',
                    'global'                  => ScopedAttributeInterface::SCOPE_STORE,
                    'visible'                 => true,
                    'required'                => false,
                    'user_defined'            => false,
                    'default'                 => '',
                    'searchable'              => false,
                    'filterable'              => false,
                    'comparable'              => false,
                    'visible_on_front'        => false,
                    'used_in_product_listing' => false,
                    'unique'                  => false,
                    'apply_to'                => '',
                    'note'                    => 'If empty, canonical will be added automatically.
                        As example can be url "http://example.com/url.html" or  "url.html"',
                ]
            );

            $entityTypeId    = $eavSetup->getEntityTypeId(Product::ENTITY);
            $attributeSetIds = $eavSetup->getAllAttributeSetIds($entityTypeId);
            foreach ($attributeSetIds as $attributeSetId) {
                /** @var int $groupId */
                $groupId = $eavSetup->getAttributeGroupId(
                    $entityTypeId,
                    $attributeSetId,
                    'Search Engine Optimization'
                );
                $eavSetup->addAttributeToGroup(
                    $entityTypeId,
                    $attributeSetId,
                    $groupId,
                    'm_seo_canonical',
                    null
                );
            }
        }

        $this->setup->endSetup();

        return $this;
    }
}
