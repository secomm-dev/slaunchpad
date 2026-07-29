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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RemoveOldTablesPatch implements DataPatchInterface
{
    private $moduleDataSetup;

    private $connection;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ResourceConnection       $connection
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->connection      = $connection;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        $tables = [
            'mst_seo_template_store',
            'mst_seo_template',
            'mst_seo_rewrite_store',
            'mst_seo_rewrite'
        ];

        foreach ($tables as $table) {
            $tableName = $this->connection->getTableName($table);

            if ($this->moduleDataSetup->tableExists($tableName)) {
                $this->moduleDataSetup->getConnection()->dropTable($tableName);
            }
        }

        $this->moduleDataSetup->endSetup();
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
