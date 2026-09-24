<?php
/**
 * Provides an independent DB connection for MoMo refund persistence.
 *
 * CreditmemoService::refund() runs the gateway refund command INSIDE a
 * transaction on the sales connection (which resolves to the same MySQL
 * connection as "default" in non-split setups). Refund evidence for
 * FAILED/UNKNOWN outcomes MUST survive the rollback that aborts the
 * creditmemo, so every refund-row write goes through this separately
 * built adapter (autocommit, own PDO handle), not through
 * ResourceConnection.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection\ConnectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;

class RefundConnectionProvider
{
    /**
     * @var ConnectionFactory
     */
    private ConnectionFactory $connectionFactory;

    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    /**
     * @var AdapterInterface|null
     */
    private ?AdapterInterface $connection = null;

    /**
     * @var string|null
     */
    private ?string $tablePrefix = null;

    /**
     * RefundConnectionProvider constructor.
     *
     * @param ConnectionFactory $connectionFactory
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        ConnectionFactory $connectionFactory,
        DeploymentConfig $deploymentConfig
    ) {
        $this->connectionFactory = $connectionFactory;
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * Get the cached independent adapter (built once per request).
     *
     * @return AdapterInterface
     */
    public function getConnection(): AdapterInterface
    {
        if ($this->connection === null) {
            $config = $this->deploymentConfig->get('db/connection/default');
            if (!is_array($config)) {
                $config = [];
            }
            $this->connection = $this->connectionFactory->create($config);
        }

        return $this->connection;
    }

    /**
     * Resolve a table name with the deployment table prefix applied.
     *
     * The independent adapter bypasses ResourceConnection, so the prefix
     * (normally added by ResourceConnection::getTableName) is applied here.
     *
     * @param string $tableName
     * @return string
     */
    public function getTableName(string $tableName): string
    {
        if ($this->tablePrefix === null) {
            $prefix = $this->deploymentConfig->get('db/table_prefix');

            $this->tablePrefix = is_string($prefix) ? $prefix : '';
        }

        return $this->tablePrefix . $tableName;
    }
}
