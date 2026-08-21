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

namespace Mageplaza\ExtraFee\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Mageplaza\ExtraFee\Helper\Data as Helper;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RebuildStats
 * @package Mageplaza\ExtraFee\Console\Command
 */
class RebuildStats extends Command
{
    const BATCH_SIZE        = 1000;
    const OPTION_BATCH_SIZE = 'batch-size';
    const OPTION_RULE_ID    = 'rule-id';
    const OPTION_DRY_RUN    = 'dry-run';

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var RuleCollectionFactory
     */
    protected $ruleCollectionFactory;

    /**
     * @var State
     */
    protected $state;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Helper $helper
     * @param RuleCollectionFactory $ruleCollectionFactory
     * @param State $state
     * @param string|null $name
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Helper $helper,
        RuleCollectionFactory $ruleCollectionFactory,
        State $state,
        ?string $name = null
    ) {
        $this->resourceConnection    = $resourceConnection;
        $this->helper                = $helper;
        $this->ruleCollectionFactory = $ruleCollectionFactory;
        $this->state                 = $state;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area code already set
        }

        $batchSize    = (int) $input->getOption(self::OPTION_BATCH_SIZE);
        $ruleIdFilter = $input->getOption(self::OPTION_RULE_ID);
        $isDryRun     = $input->getOption(self::OPTION_DRY_RUN);

        $connection = $this->resourceConnection->getConnection();
        $statsTable = $this->resourceConnection->getTableName('mageplaza_extrafee_stats');

        $output->writeln('<info>Starting Extra Fee Stats Rebuild...</info>');
        if ($isDryRun) {
            $output->writeln('<comment>DRY RUN MODE - No changes will be made</comment>');
        }
        $output->writeln('');

        // Get all active rules
        $ruleCollection = $this->ruleCollectionFactory->create();
        if ($ruleIdFilter) {
            $ruleCollection->addFieldToFilter('rule_id', (int) $ruleIdFilter);
        }

        $rules = $ruleCollection->getItems();
        if (!$rules) {
            $output->writeln('<error>No rules found</error>');

            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf('<info>Processing %d rule(s)</info>', count($rules)));
        $output->writeln('');

        foreach ($rules as $rule) {
            $ruleId = (int) $rule->getId();
            $output->writeln(sprintf('<comment>Rule #%d: %s</comment>', $ruleId, $rule->getName()));

            try {
                $orderCount = $this->calculateOrderCount($ruleId, $output);
                $output->writeln(sprintf('  Order Count: %d', $orderCount));

                $revenue = $this->calculateRevenue($ruleId, $batchSize, $output);
                $output->writeln(sprintf('  Revenue: %.2f', $revenue));

                if (!$isDryRun) {
                    $connection->insertOnDuplicate(
                        $statsTable,
                        [
                            'rule_id'     => $ruleId,
                            'order_count' => $orderCount,
                            'revenue'     => $revenue
                        ],
                        ['order_count', 'revenue']
                    );
                    $output->writeln('  <info>✓ Stats updated</info>');
                } else {
                    $output->writeln('  <comment>✓ Would update stats (dry run)</comment>');
                }

                $output->writeln('');
            } catch (\Exception $e) {
                $output->writeln(sprintf('  <error>Error: %s</error>', $e->getMessage()));
                $output->writeln('');
            }
        }

        $output->writeln('<info>Stats rebuild completed!</info>');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Calculate order count for a rule using direct SQL query
     *
     * @param int $ruleId
     * @param OutputInterface $output
     *
     * @return int
     */
    public function calculateOrderCount(int $ruleId, OutputInterface $output): int
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $connection->getTableName('sales_order');

        // Count orders containing this rule (excluding pending/canceled/closed)
        $select = $connection->select()
            ->from($orderTable, ['count' => new \Zend_Db_Expr('COUNT(DISTINCT entity_id)')])
            ->where('status NOT IN (?)', ['pending', 'canceled', 'closed'])
            ->where('mp_extra_fee IS NOT NULL')
            ->where('mp_extra_fee LIKE ?', '%mp_extra_fee_rule_' . $ruleId . '%');

        return (int) $connection->fetchOne($select);
    }

    /**
     * Calculate revenue for a rule with batched processing
     *
     * @param int $ruleId
     * @param int $batchSize
     * @param OutputInterface $output
     *
     * @return float
     */
    public function calculateRevenue(int $ruleId, int $batchSize, OutputInterface $output): float
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $connection->getTableName('sales_order');

        // Get all order IDs that have this rule
        $orderIds = $connection->fetchCol(
            $connection->select()
                ->from($orderTable, ['entity_id'])
                ->where('status NOT IN (?)', ['pending', 'canceled'])
                ->where('mp_extra_fee IS NOT NULL')
                ->where('mp_extra_fee LIKE ?', '%mp_extra_fee_rule_' . $ruleId . '%')
        );

        if (!$orderIds) {
            return 0.0;
        }

        $totalRevenue = 0.0;
        $totalOrders  = count($orderIds);
        $batches      = array_chunk($orderIds, $batchSize);

        $output->writeln(sprintf('  Processing %d orders in %d batches...', $totalOrders, count($batches)));

        $progressBar = new ProgressBar($output, count($batches));
        $progressBar->setFormat('  [%bar%] %percent:3s%% - %current%/%max% batches');
        $progressBar->start();

        foreach ($batches as $batchOrderIds) {
            $batchRevenue = $this->calculateRevenueBatch($ruleId, $batchOrderIds);
            $totalRevenue += $batchRevenue;
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');

        return $totalRevenue;
    }

    /**
     * Calculate revenue for a batch of orders
     *
     * @param int $ruleId
     * @param array $orderIds
     *
     * @return float
     */
    public function calculateRevenueBatch(int $ruleId, array $orderIds): float
    {
        $connection          = $this->resourceConnection->getConnection();
        $invoiceItemTable    = $connection->getTableName('sales_invoice_item');
        $creditmemoItemTable = $connection->getTableName('sales_creditmemo_item');
        $invoiceTable        = $connection->getTableName('sales_invoice');
        $creditmemoTable     = $connection->getTableName('sales_creditmemo');

        $revenue = 0.0;

        // Calculate invoice revenue using SQL aggregation
        $invoiceSelect = $connection->select()
            ->from(['ii' => $invoiceItemTable], [])
            ->join(
                ['i' => $invoiceTable],
                'ii.parent_id = i.entity_id',
                []
            )
            ->where('i.order_id IN (?)', $orderIds)
            ->where('ii.mp_extra_fee IS NOT NULL')
            ->where('ii.mp_extra_fee LIKE ?', '%mp_extra_fee_rule_' . $ruleId . '%')
            ->columns(['mp_extra_fee', 'qty']);

        $invoiceItems = $connection->fetchAll($invoiceSelect);

        foreach ($invoiceItems as $item) {
            $feeData = $this->helper->unserialize($item['mp_extra_fee']) ?: [];
            foreach ($feeData as $total) {
                if (!isset($total['code']) || !isset($total['base_value'])) {
                    continue;
                }
                $parts = array_filter(preg_split('/\D+/', $total['code']));
                $rid   = (int) reset($parts);
                if ($rid == $ruleId) {
                    $revenue += (float) $total['base_value'] * (float) $item['qty'];
                }
            }
        }

        // Calculate credit memo revenue using SQL aggregation
        $creditmemoSelect = $connection->select()
            ->from(['ci' => $creditmemoItemTable], [])
            ->join(
                ['c' => $creditmemoTable],
                'ci.parent_id = c.entity_id',
                []
            )
            ->where('c.order_id IN (?)', $orderIds)
            ->where('ci.mp_extra_fee IS NOT NULL')
            ->where('ci.mp_extra_fee LIKE ?', '%mp_extra_fee_rule_' . $ruleId . '%')
            ->columns(['mp_extra_fee', 'qty']);

        $creditmemoItems = $connection->fetchAll($creditmemoSelect);

        foreach ($creditmemoItems as $item) {
            $feeData = $this->helper->unserialize($item['mp_extra_fee']) ?: [];
            foreach ($feeData as $total) {
                if (!isset($total['code']) || !isset($total['base_value'])) {
                    continue;
                }
                // Only refundable items (rf = 1)
                if (!isset($total['rf']) || (int) $total['rf'] !== 1) {
                    continue;
                }
                $parts = array_filter(preg_split('/\D+/', $total['code']));
                $rid   = (int) reset($parts);
                if ($rid == $ruleId) {
                    $revenue -= (float) $total['base_value'] * (float) $item['qty'];
                }
            }
        }

        return $revenue;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('mageplaza:extrafee:rebuild-stats')
            ->setDescription('Rebuild extra fee statistics from existing orders, invoices, and credit memos')
            ->addOption(
                self::OPTION_BATCH_SIZE,
                'b',
                InputOption::VALUE_OPTIONAL,
                'Batch size for processing orders (default: 1000)',
                self::BATCH_SIZE
            )
            ->addOption(
                self::OPTION_RULE_ID,
                'r',
                InputOption::VALUE_OPTIONAL,
                'Rebuild stats for specific rule ID only'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                'd',
                InputOption::VALUE_NONE,
                'Dry run mode - show what would be done without making changes'
            );

        parent::configure();
    }
}
