<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\Sync\MasterDataSynchronizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SPEC-FEAT-FQWEQ3 §8 — GHN master data synchronization (CLI-only, independent per scheme).
 */
class SyncAddressCommand extends Command
{
    public const COMMAND_NAME = 'secomm:ghn:address:sync';

    public function __construct(private readonly MasterDataSynchronizer $synchronizer)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setDescription('Sync GHN administrative master data into secomm_ghn_address_unit (one scheme per run)')
            ->addOption(
                'scheme',
                null,
                InputOption::VALUE_REQUIRED,
                'GHN scheme: GHN_ADMIN_2025 or GHN_ADMIN_PRE_2025'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Fetch and report without writing'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scheme = (string) $input->getOption('scheme');
        if ($scheme === '') {
            throw new LocalizedException(__('Option --scheme is required.'));
        }

        $report = $this->synchronizer->sync($scheme, (bool) $input->getOption('dry-run'));

        $output->writeln(sprintf('<info>Scheme: %s</info>', $report['scheme']));
        $output->writeln(sprintf('Dry run: %s', $report['dry_run'] ? 'yes' : 'no'));
        $output->writeln(sprintf('Fetched: %d', $report['fetched']));
        foreach ($report['by_depth'] as $depth => $count) {
            $output->writeln(sprintf('  depth %d: %d', $depth, $count));
        }
        $output->writeln(sprintf('Known before: %d', $report['present_before']));
        $output->writeln(sprintf('Disabled (missing from snapshot): %d', $report['disabled']));
        $output->writeln(sprintf('Source version: %s', $report['source_version']));

        return Cli::RETURN_SUCCESS;
    }
}
