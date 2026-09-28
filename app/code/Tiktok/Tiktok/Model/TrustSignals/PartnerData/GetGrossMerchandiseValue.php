<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\TrustSignals\PartnerData;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;

class GetGrossMerchandiseValue
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get gross merchandise value
     *
     * @param string $storeId
     * @return int
     */
    public function execute(string $storeId): int
    {
        $orderTableName = $this->resourceConnection->getTableName(
            'sales_order'
        );
        $connection = $this->resourceConnection->getConnection();
        $field = OrderInterface::SUBTOTAL;
        $select = $connection->select()
            ->from($orderTableName, ['total' => new \Zend_Db_Expr("SUM({$field})")]);

        $timestamp = strtotime('-30 days');
        $date =  date('Y-m-d', $timestamp);
        $select->where('created_at >= ?', $date);
        $select->where('state NOT IN (?)', ['canceled', 'closed']);
        $select->where('store_id = ?', (int)$storeId);
        return (int)$connection->fetchOne($select);
    }
}
