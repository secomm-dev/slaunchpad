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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

/**
 * Class UpdateStatsOrder
 * @package Mageplaza\ExtraFee\Observer
 */
class UpdateStatsOrder implements ObserverInterface
{
    /** @var ResourceConnection */
    protected $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getId()) {
            return;
        }

        // Get current and original status
        $currentStatus  = $order->getStatus();
        $originalStatus = $order->getOrigData('status');

        // Define excluded statuses that should not be counted
        $excludedStatuses = ['pending', 'canceled', 'closed'];
        $wasExcluded      = !$originalStatus || in_array($originalStatus, $excludedStatuses);
        $isNowValid       = !in_array($currentStatus, $excludedStatuses);

        if (!($wasExcluded && $isNowValid)) {
            return;
        }

        $extraFeeJson = $order->getMpExtraFee();
        if (!$extraFeeJson) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $statsTable = $connection->getTableName('mageplaza_extrafee_stats');

        try {
            $extraFee = json_decode($extraFeeJson, true);
            if (!is_array($extraFee) || !isset($extraFee['totals'])) {
                return;
            }

            $ruleIds = [];
            foreach ($extraFee['totals'] as $total) {
                if (!isset($total['code'])) {
                    continue;
                }
                $parts = array_filter(preg_split('/\D+/', $total['code']));
                $rid   = (int) reset($parts);
                if ($rid) {
                    $ruleIds[$rid] = true;
                }
            }
            if (!$ruleIds) {
                return;
            }

            $values = [];
            foreach (array_keys($ruleIds) as $ruleId) {
                $values[] = '(' . (int) $ruleId . ',1,0)';
            }

            $sql = 'INSERT INTO ' . $statsTable . ' (rule_id, order_count, revenue) VALUES ' . implode(',', $values)
                . ' ON DUPLICATE KEY UPDATE order_count = order_count + 1';
            $connection->query($sql);

        } catch (\Throwable $e) {
            // swallow to avoid blocking order save
        }
    }
}
