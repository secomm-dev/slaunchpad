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



namespace Mirasvit\SeoContent\Setup\Patch\Data;

use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class DataPatch102 implements DataPatchInterface, PatchVersionInterface
{
    private $setup;

    private $categorySetupFactory;

    public function __construct(
        ModuleDataSetupInterface $setup,
        CategorySetupFactory $categorySetupFactory
    ) {
        $this->setup = $setup;
        $this->categorySetupFactory = $categorySetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [DataPatch101::class];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '1.0.2';
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

        $catalogSetup = $this->categorySetupFactory->create(['setup' => $setup]);

        $catalogSetup->addAttribute(
            \Magento\Catalog\Model\Category::ENTITY,
            'title_h1',
            [
                'group'            => 'General information',
                'type'             => 'varchar',
                'backend'          => '',
                'frontend'         => '',
                'label'            => 'H1 Title',
                'input'            => 'text',
                'class'            => '',
                'source'           => '',
                'global'           => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE,
                'visible'          => true,
                'required'         => false,
                'user_defined'     => false,
                'default'          => '',
                'apply_to'         => '',
                'visible_on_front' => false,
                'sort_order'       => 10,
            ]
        );

        $this->setup->endSetup();

        return $this;
    }
}
