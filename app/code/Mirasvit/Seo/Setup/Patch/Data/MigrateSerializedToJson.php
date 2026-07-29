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


declare(strict_types=1);

namespace Mirasvit\Seo\Setup\Patch\Data;

use InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * One-time sweep that converts legacy PHP-serialize values across module-seo's
 * serialized columns and config paths to JSON. Necessary because
 * Mirasvit\Core\Service\SerializeService::decode() is JSON-only after the
 * recent core release — any row left over from pre-Magento-2.2 installs
 * would otherwise fail to decode at admin load.
 *
 * Rows are touched only when the value matches the PHP-serialize array
 * prefix; rows already on JSON are skipped. Decoding goes through Magento's
 * Serialize class (allowed_classes => false) so a poisoned legacy row
 * cannot trigger PHP object injection during migration.
 */
class MigrateSerializedToJson implements DataPatchInterface
{
    /**
     * @var array<int, array{table: string, idColumn: string, valueColumn: string}>
     */
    private const TABLES = [
        ['table' => 'mst_seo_canonical_rewrite', 'idColumn' => 'canonical_rewrite_id', 'valueColumn' => 'conditions_serialized'],
        ['table' => 'mst_seo_canonical_rewrite', 'idColumn' => 'canonical_rewrite_id', 'valueColumn' => 'actions_serialized'],
        ['table' => 'mst_seo_content_template', 'idColumn' => 'template_id',          'valueColumn' => 'conditions_serialized'],
        ['table' => 'mst_seo_audit_job',        'idColumn' => 'job_id',               'valueColumn' => 'result_serialized'],
        ['table' => 'mst_reach_snippet_extender','idColumn' => 'extender_id',         'valueColumn' => 'conditions_serialized'],
    ];

    /**
     * @var array<int, string>
     */
    private const CONFIG_PATHS = [
        'seo/general/noindex_pages2',
        'seo/general/alternate_configurable',
        'seo/general/configurable_hreflang_x_default',
        'seo/general/canonical_layered_config',
        'seo/extended/to_lowercase_allowed_types',
    ];

    private $moduleDataSetup;

    private $jsonSerializer;

    private $phpSerializer;

    private $logger;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        Json                     $jsonSerializer,
        Serialize                $phpSerializer,
        LoggerInterface          $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->jsonSerializer  = $jsonSerializer;
        $this->phpSerializer   = $phpSerializer;
        $this->logger          = $logger;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        foreach (self::TABLES as $spec) {
            $this->migrateTableColumn($spec['table'], $spec['idColumn'], $spec['valueColumn']);
        }

        $this->migrateConfigPaths();

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function migrateTableColumn(string $table, string $idColumn, string $valueColumn): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $tableName  = $this->moduleDataSetup->getTable($table);

        if (!$connection->isTableExists($tableName)) {
            return;
        }

        $rows = $connection->fetchAll(
            $connection->select()->from($tableName, [$idColumn, $valueColumn])
        );

        foreach ($rows as $row) {
            $serialized = (string) $row[$valueColumn];

            if (!preg_match('/^a:\d+:\{/', $serialized)) {
                continue;
            }

            try {
                $decoded = $this->phpSerializer->unserialize($serialized);
            } catch (InvalidArgumentException $e) {
                $this->logger->warning(sprintf(
                    'Mirasvit_Seo migration: %s.%s for %s=%s is unparseable; left untouched.',
                    $table,
                    $valueColumn,
                    $idColumn,
                    $row[$idColumn]
                ));
                continue;
            }

            if (!is_array($decoded)) {
                continue;
            }

            $connection->update(
                $tableName,
                [$valueColumn => $this->jsonSerializer->serialize($decoded)],
                [$idColumn . ' = ?' => $row[$idColumn]]
            );
        }
    }

    private function migrateConfigPaths(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $tableName  = $this->moduleDataSetup->getTable('core_config_data');

        if (!$connection->isTableExists($tableName)) {
            return;
        }

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($tableName, ['config_id', 'path', 'value'])
                ->where('path IN (?)', self::CONFIG_PATHS)
        );

        foreach ($rows as $row) {
            $serialized = (string) $row['value'];

            if (!preg_match('/^a:\d+:\{/', $serialized)) {
                continue;
            }

            try {
                $decoded = $this->phpSerializer->unserialize($serialized);
            } catch (InvalidArgumentException $e) {
                $this->logger->warning(sprintf(
                    'Mirasvit_Seo migration: core_config_data path=%s (config_id=%s) is unparseable; left untouched.',
                    $row['path'],
                    $row['config_id']
                ));
                continue;
            }

            if (!is_array($decoded)) {
                continue;
            }

            $connection->update(
                $tableName,
                ['value' => $this->jsonSerializer->serialize($decoded)],
                ['config_id = ?' => $row['config_id']]
            );
        }
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
