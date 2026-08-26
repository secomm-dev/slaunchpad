<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Console\Command;

use Secomm\PaymentCore\Model\Lifecycle\ExpirePayments;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TASK-Q2BAHW — manual trigger of the expiry cron for QC (spec §8). Runs the
 * exact same ExpirePayments::execute() the scheduled job runs — one code path,
 * no drift. The cron entry keeps calling execute() with no arguments.
 */
class ExpirePaymentsCommand extends Command
{
    public const COMMAND_NAME = 'paymentcore:expire:run';

    private const ARG_ORDER_ID = 'order-id';
    private const OPT_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly ExpirePayments $expirePayments,
        ?string $name = null
    ) {
        parent::__construct($name ?? self::COMMAND_NAME);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription(
            'Payment Core: process expired pending-payment orders now (same logic as the secomm_paymentcore cron)'
        );
        $this->addArgument(
            self::ARG_ORDER_ID,
            InputArgument::OPTIONAL,
            'Process only this order (entity id) — skips the expiry-window check is NOT implied; the order must still have an active expired record'
        );
        $this->addOption(
            self::OPT_DRY_RUN,
            'd',
            InputOption::VALUE_NONE,
            'List candidate records without cancelling anything'
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orderId = $input->getArgument(self::ARG_ORDER_ID);
        $orderId = is_numeric($orderId) ? (int)$orderId : null;
        $dryRun = (bool)$input->getOption(self::OPT_DRY_RUN);

        if ($dryRun) {
            $candidates = $this->expirePayments->getCandidates($orderId);
            if ($candidates === []) {
                $output->writeln('<info>No expired active records.</info>');
                return Command::SUCCESS;
            }
            foreach ($candidates as $record) {
                $output->writeln(sprintf(
                    '<comment>order %d method %s expires_at %s retry %d</comment>',
                    $record->getOrderId(),
                    $record->getMethodCode(),
                    $record->getExpiresAt(),
                    $record->getRetryCount()
                ));
            }
            $output->writeln(sprintf('<info>%d candidate(s) — nothing processed (dry-run).</info>', count($candidates)));
            return Command::SUCCESS;
        }

        $processed = $this->expirePayments->execute($orderId);
        $output->writeln(sprintf('<info>Processed %d expired record(s). See var/log/secomm_paymentcore.log for outcomes.</info>', $processed));
        return Command::SUCCESS;
    }
}
