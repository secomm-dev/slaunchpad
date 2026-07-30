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

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ChangeProductBreadcrumbsConfigPaths implements DataPatchInterface
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

        $table = $setup->getTable('core_config_data');

        $newConfigRows = $this->setup->getConnection()->fetchAll("SELECT * FROM {$table} WHERE path='seo/product_breadcrumbs/appearance'");
        $oldConfigRows = $this->setup->getConnection()->fetchAll("SELECT * FROM {$table} WHERE path='seo/general/product_breadcrumbs/appearance'");
        foreach ($oldConfigRows as $oldConfig) {
            if (!$this->isNewConfigExist($oldConfig, $newConfigRows)) {
                $oldConfigValue = $oldConfig['value'];
                $scope          = $oldConfig['scope'];
                $scopeId        = (int)$oldConfig['scope_id'];
                $this->setup->getConnection()->insert(
                    $table,
                    ['scope' => $scope, 'scope_id' => $scopeId, 'path' => 'seo/product_breadcrumbs/appearance', 'value' => $oldConfigValue,]
                );
            }
        }

        $newConfigRows = $this->setup->getConnection()->fetchAll("SELECT * FROM {$table} WHERE path='seo/product_breadcrumbs/format'");
        $oldConfigRows = $this->setup->getConnection()->fetchAll("SELECT * FROM {$table} WHERE path='seo/general/product_breadcrumbs/format'");
        foreach ($oldConfigRows as $oldConfig) {
            if (!$this->isNewConfigExist($oldConfig, $newConfigRows)) {
                $oldConfigValue = $oldConfig['value'];
                $scope          = $oldConfig['scope'];
                $scopeId        = (int)$oldConfig['scope_id'];
                $this->setup->getConnection()->insert(
                    $table,
                    ['scope' => $scope, 'scope_id' => $scopeId, 'path' => 'seo/product_breadcrumbs/format', 'value' => $oldConfigValue,]
                );
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
