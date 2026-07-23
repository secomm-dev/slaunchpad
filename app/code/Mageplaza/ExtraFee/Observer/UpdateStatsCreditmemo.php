<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
namespace Mageplaza\ExtraFee\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Class UpdateStatsCreditmemo
 * @package Mageplaza\ExtraFee\Observer
 */
class UpdateStatsCreditmemo implements ObserverInterface
{
    /** @var ResourceConnection */
    protected $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function execute(Observer $observer)
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo || !$creditmemo->getId()) {
            return;
        }
        $connection = $this->resourceConnection->getConnection();
        $table      = $connection->getTableName('mageplaza_extrafee_stats');

        try {
            $ruleTotals = [];
            foreach ($creditmemo->getItems() as $item) {
                $feeJson = $item->getData('mp_extra_fee');
                if (!$feeJson) {
                    continue;
                }
                $feeData = json_decode($feeJson, true) ?: [];
                foreach ($feeData as $total) {
                    if (!isset($total['code']) || !isset($total['base_value'])) {
                        continue;
                    }
                    // only refundable ('rf' == 1) contributes
                    if (isset($total['rf']) && (int) $total['rf'] !== 1) {
                        continue;
                    }
                    $parts = array_filter(preg_split('/\D+/', $total['code']));
                    $rid   = (int) reset($parts);
                    if ($rid) {
                        $amount           = (float) $total['base_value'] * (float) $item->getQty();
                        $ruleTotals[$rid] = ($ruleTotals[$rid] ?? 0) + $amount;
                    }
                }
            }
            foreach ($ruleTotals as $ruleId => $amount) {
                // subtract revenue for refunds
                $connection->insertOnDuplicate($table, [
                    'rule_id'     => (int) $ruleId,
                    'order_count' => 0,
                    'revenue'     => 0,
                ], ['revenue' => new \Zend_Db_Expr('revenue - ' . $connection->quote($amount))]);
            }
        } catch (\Throwable $e) {
            // swallow
        }
    }
}
