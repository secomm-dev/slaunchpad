<?php
namespace Mageplaza\TableRateShipping\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetup;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validator\ValidateException;
use Mageplaza\TableRateShipping\Helper\Data;

/**
 * Class AddShippingGroupAttribute
 * @package Vendor\ModuleName\DataPatch
 */
class AddShippingGroupAttribute implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;
    /**
     * @var CategorySetupFactory
     */
    private $categorySetupFactory;

    /**
     * AddShippingGroupAttribute constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CategorySetupFactory $categorySetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategorySetupFactory $categorySetupFactory
    )
    {
        $this->moduleDataSetup  = $moduleDataSetup;
        $this->categorySetupFactory = $categorySetupFactory;
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws ValidateException
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup;
        /** @var CategorySetup $catalogSetup */
        $catalogSetup = $this->categorySetupFactory->create(['setup' => $setup]);

        $catalogSetup->addAttribute(Product::ENTITY, Data::SHIP_TYPE_ATTR, [
            'group'                   => 'Product Details',
            'label'                   => 'Shipping Group',
            'type'                    => 'text',
            'input'                   => 'select',
            'source'                  => Table::class,
            'global'                  => ScopedAttributeInterface::SCOPE_WEBSITE,
            'sort_order'              => 300,
            'backend'                 => '',
            'frontend'                => '',
            'class'                   => '',
            'visible'                 => true,
            'required'                => false,
            'user_defined'            => true,
            'default'                 => '',
            'searchable'              => true,
            'filterable'              => true,
            'comparable'              => false,
            'visible_on_front'        => false,
            'unique'                  => false,
            'used_in_product_listing' => true,
            'is_used_for_promo_rules' => true,
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
