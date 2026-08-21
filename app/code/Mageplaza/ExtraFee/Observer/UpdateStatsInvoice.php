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
use Magento\Sales\Model\Order\Invoice;
use Mageplaza\ExtraFee\Helper\Data as Helper;

/**
 * Class UpdateStatsInvoice
 * @package Mageplaza\ExtraFee\Observer
 */
class UpdateStatsInvoice implements ObserverInterface
{
    /** @var ResourceConnection */
    protected $resourceConnection;

    /** @var Helper */
    protected $helper;

    public function __construct(ResourceConnection $resourceConnection, Helper $helper)
    {
        $this->resourceConnection = $resourceConnection;
        $this->helper             = $helper;
    }

    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        if (!$invoice || !$invoice->getId()) {
            return;
        }
        $order = $invoice->getOrder();
        if (!$order) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('mageplaza_extrafee_stats');

        try {
            // sum revenue contributions per rule from this invoice
            $ruleTotals = [];
            foreach ($invoice->getItemsCollection() as $item) {
                $feeJson = $item->getMpExtraFee();
                if (!$feeJson) {
                    continue;
                }
                $feeData = json_decode($feeJson, true) ?: [];
                foreach ($feeData as $total) {
                    if (!isset($total['code']) || !isset($total['base_value'])) {
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
                $connection->insertOnDuplicate($table, [
                    'rule_id'     => (int) $ruleId,
                    'order_count' => 0,
                    'revenue'     => $amount,
                ], ['revenue' => new \Zend_Db_Expr('revenue + ' . $connection->quote($amount))]);
            }
        } catch (\Throwable $e) {
            // swallow
        }
    }
}
