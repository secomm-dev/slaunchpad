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

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ChangeConfigPaths implements DataPatchInterface
{
    private $setup;

    public function __construct(
        ModuleDataSetupInterface $setup
    ) {
        $this->setup = $setup;
    }

    public function apply(): void
    {
        $this->setup->getConnection()->startSetup();
        $setup = $this->setup;

        $oldPrefix = 'seo/';
        $newPaths = [
            'seo_content/meta/is_add_prefix_suffix',
            'seo_content/limiter/is_use_html_symbols_in_meta_tags',
            'seo_content/meta/is_category_meta_tags_used',
            'seo_content/meta/is_product_meta_tags_used',
            'seo_content/meta/is_cms_meta_tags_used',
            'seo_content/meta/is_blog_meta_tags_used',
            'seo_content/meta/is_brand_meta_tags_used',
            'seo_content/limiter/meta_title_max_length',
            'seo_content/limiter/meta_description_max_length',
            'seo_content/limiter/product_name_max_length',
            'seo_content/limiter/product_short_description_max_length',
            'seo_content/pagination/meta_title_page_number',
            'seo_content/pagination/meta_description_page_number',
        ];

        $table = $setup->getTable('core_config_data');

        foreach ($newPaths as $newPath) {
            $oldPath = $oldPrefix . $newPath;
            $newConfigRows = $this->setup->getConnection()->fetchAll("SELECT * FROM {$table} WHERE path='{$newPath}'");
            $oldConfigRows = $this->setup->getConnection()->fetchAll("SELECT * FROM {$table} WHERE path='{$oldPath}'");
            foreach ($oldConfigRows as $oldConfig) {
                if (!$this->isNewConfigExist($oldConfig, $newConfigRows)) {
                    $oldConfigValue = $oldConfig['value'];
                    $scope          = $oldConfig['scope'];
                    $scopeId        = (int)$oldConfig['scope_id'];
                    $this->setup->getConnection()->insert(
                        $table,
                        ['scope' => $scope, 'scope_id' => $scopeId, 'path' => $newPath, 'value' => $oldConfigValue,]
                    );
                }
            }
        }

        $this->setup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function isNewConfigExist(array $config, array $newConfigRows): bool
    {
        foreach ($newConfigRows as $newConfigRow) {
            if ($config['scope'] == $newConfigRow['scope']
                && $config['scope_id'] == $newConfigRow['scope_id']) {
                return true;
            }
        }

        return false;
    }
}
