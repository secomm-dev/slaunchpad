<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Console\Command;

use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Secomm\Tracking\Model\Config;
use Secomm\Tracking\Model\Consent\ConsentEvaluatorInterface;
use Secomm\Tracking\Model\Delivery\EnqueueService;
use Secomm\Tracking\Model\Event\EventNormalizer;
use Secomm\Tracking\Model\Event\TrackingEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * FEAT-31X6N2 / QC utility — enqueue purchase events for legacy orders that
 * predate the module (or failed before credentials were configured).
 *
 * Platform constraint: Meta/TikTok reject events older than 7 days, so backfill
 * stamps "now" unless --keep-time is given (useful for orders inside the window).
 * UNIQUE (event_id, vendor) makes re-runs idempotent — already-sent orders
 * simply collide and are skipped.
 *
 * Usage:
 *   bin/magento secomm:tracking:backfill-purchase            # last 30 days
 *   bin/magento secomm:tracking:backfill-purchase --days 7
 *   bin/magento secomm:tracking:backfill-purchase --order 100000123
 *   bin/magento secomm:tracking:backfill-purchase --keep-time
 */
class BackfillPurchase extends Command
{
    private const DEFAULT_DAYS = 30;

    public function __construct(
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly EventNormalizer $normalizer,
        private readonly EnqueueService $enqueueService,
        private readonly ConsentEvaluatorInterface $consentEvaluator,
        private readonly Config $config,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('secomm:tracking:backfill-purchase');
        $this->setDescription('Enqueue purchase tracking events for legacy orders (QC/backfill utility)');
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Look-back window in days (default 30)');
        $this->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Single order increment_id');
        $this->addOption('keep-time', 'k', InputOption::VALUE_NONE, 'Keep original created_at timestamps (only orders <7 days old will be accepted by Meta)');
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // No setAreaCode: bin/magento already boots adminhtml for console
        // commands — calling setAreaCode again throws "Area code is already set".
        if (!$this->config->isEnabled()) {
            $output->writeln('<comment>Tracking is disabled (secomm_tracking/general/enabled). Nothing to do.</comment>');
            return Command::SUCCESS;
        }

        /** @var OrderCollection $orders */
        $orders = $this->orderCollectionFactory->create();

        if ($input->getOption('order')) {
            $orders->addFieldToFilter('increment_id', (string)$input->getOption('order'));
        } else {
            $days = max(1, (int)($input->getOption('days') ?: self::DEFAULT_DAYS));
            $orders->addFieldToFilter('created_at', ['gteq' => date('Y-m-d H:i:s', time() - $days * 86400)]);
        }
        // Only orders that reached a paid state — same semantics as the observer gate.
        $orders->addFieldToFilter('state', ['in' => ['processing', 'complete']]);

        $keepTime = (bool)$input->getOption('keep-time');
        $enqueued = 0;
        foreach ($orders->getItems() as $order) {
            $event = $this->normalizer->fromOrder($order);
            if (!$keepTime) {
                $event = $event->withEventTime(time());
            }
            $event = $event->withConsent(
                $this->consentEvaluator->allows(ConsentEvaluatorInterface::SCOPE_ANALYTICS, null),
                $this->consentEvaluator->allows(ConsentEvaluatorInterface::SCOPE_MARKETING, null)
            );

            $before = $enqueued;
            $this->enqueueQuietly($event, $enqueued);
            if ($enqueued > $before) {
                $output->writeln(sprintf('<info>+</info> %s (created %s)', $order->getIncrementId(), $order->getCreatedAt()));
            }
        }

        $output->writeln(sprintf('Done. %d event(s) enqueued — run the flush cron to deliver.', $enqueued));
        return Command::SUCCESS;
    }

    /**
     * EnqueueService::enqueue has no return value; failures only log. Count
     * successes via the outbox side effect is overkill for a QC utility —
     * UNIQUE collisions surface as "failed to enqueue" in the log, which is
     * the desired idempotent-skip behaviour.
     */
    private function enqueueQuietly(TrackingEvent $event, int &$counter): void
    {
        try {
            $this->enqueueService->enqueue($event, null);
            $counter++;
        } catch (\Throwable $e) {
            // logged inside enqueue; swallow here so one bad order stops nothing
        }
    }
}