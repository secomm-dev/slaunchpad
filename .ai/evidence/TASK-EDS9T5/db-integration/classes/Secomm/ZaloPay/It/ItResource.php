<?php
/**
 * Integration-test scaffolding — NOT production code.
 *
 * PaymentAttemptResource with the constructor DI neutralised: it hands the
 * collection a REAL Pdo\Mysql adapter and the REAL production main-table
 * name (same string constant as the production _construct()).
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\It;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource;

class ItResource extends PaymentAttemptResource
{
    /**
     * Real adapter, injected by the driver script.
     *
     * @var AdapterInterface|null
     */
    public ?AdapterInterface $conn = null;

    /**
     * Real main table name (verbatim from production _construct()).
     *
     * @var string
     */
    public const MAIN_TABLE = 'secomm_zalopay_payment_attempt';

    /**
     * Skip AbstractDb's Context DI.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * @return AdapterInterface|null
     */
    public function getConnection(): ?AdapterInterface
    {
        return $this->conn;
    }

    /**
     * @return string
     */
    public function getMainTable(): string
    {
        return self::MAIN_TABLE;
    }

    /**
     * Identity table-name mapping: this throwaway database has no table
     * prefix, which is exactly what the real getTable() resolves to when
     * no prefix is configured. Avoids wiring ResourceConnection.
     *
     * @param string $tableName
     * @return string
     */
    public function getTable($tableName)
    {
        return $tableName;
    }
}
