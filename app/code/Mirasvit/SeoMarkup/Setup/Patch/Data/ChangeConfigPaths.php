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



namespace Mirasvit\SeoMarkup\Setup\Patch\Data;

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
            'seo_markup/product/is_remove_native_rs',
            'seo_markup/product/is_rs_enabled',
            'seo_markup/product/is_price_enabled',
            'seo_markup/product/description_type',
            'seo_markup/product/is_image_enabled',
            'seo_markup/product/is_availability_enabled',
            'seo_markup/product/is_accepted_payment_method_enabled',
            'seo_markup/product/is_available_delivery_method_enabled',
            'seo_markup/product/is_category_enabled',
            'seo_markup/product/is_mpn_enabled',
            'seo_markup/product/mpn_attribute',
            'seo_markup/product/brand_attribute',
            'seo_markup/product/model_attribute',
            'seo_markup/product/color_attribute',
            'seo_markup/product/size_attribute',
            'seo_markup/product/weight_unit_type',
            'seo_markup/product/is_dimensions_enabled',
            'seo_markup/product/dimension_unit',
            'seo_markup/product/dimension_height_attribute',
            'seo_markup/product/dimension_width_attribute',
            'seo_markup/product/dimension_depth_attribute',
            'seo_markup/product/item_condition_type',
            'seo_markup/product/item_condition_attribute',
            'seo_markup/product/item_condition_attribute_value_new',
            'seo_markup/product/item_condition_attribute_value_used',
            'seo_markup/product/item_condition_attribute_value_refurbished',
            'seo_markup/product/item_condition_attribute_value_damaged',
            'seo_markup/product/is_individual_reviews_enabled',
            'seo_markup/product/is_variants_enabled',
            'seo_markup/product/gtin',
            'seo_markup/product/gtin8_attribute',
            'seo_markup/product/gtin12_attribute',
            'seo_markup/product/gtin13_attribute',
            'seo_markup/product/gtin14_attribute',
            'seo_markup/category/is_remove_native_rs',
            'seo_markup/category/is_rs_enabled',
            'seo_markup/category/description_type',
            'seo_markup/category/is_image_enabled',
            'seo_markup/category/product_offers_type',
            'seo_markup/category/product_offers_format',
            'seo_markup/category/is_og_enabled',
            'seo_markup/page/is_remove_native_rs',
            'seo_markup/page/is_og_enabled',
            'seo_markup/organization/is_rs_enabled',
            'seo_markup/organization/is_custom_name',
            'seo_markup/organization/custom_name',
            'seo_markup/organization/is_custom_address_country',
            'seo_markup/organization/custom_address_country',
            'seo_markup/organization/is_custom_address_locality',
            'seo_markup/organization/custom_address_locality',
            'seo_markup/organization/is_custom_address_region',
            'seo_markup/organization/custom_address_region',
            'seo_markup/organization/is_custom_postal_code',
            'seo_markup/organization/custom_postal_code',
            'seo_markup/organization/is_custom_street_address',
            'seo_markup/organization/custom_street_address',
            'seo_markup/organization/is_custom_telephone',
            'seo_markup/organization/custom_telephone',
            'seo_markup/organization/custom_fax_number',
            'seo_markup/organization/is_custom_email',
            'seo_markup/organization/custom_email',
            'seo_markup/organization/youtube_link',
            'seo_markup/organization/facebook_link',
            'seo_markup/organization/linkedin_link',
            'seo_markup/organization/instagram_link',
            'seo_markup/organization/pinterest_link',
            'seo_markup/organization/tumblr_link',
            'seo_markup/organization/twitter_link',
            'seo_markup/breadcrumb_list/is_rs_enabled',
            'seo_markup/twitter/card_type',
            'seo_markup/twitter/username',
            'seo_markup/searchbox/searchbox_type',
            'seo_markup/searchbox/blog_search_url',
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
