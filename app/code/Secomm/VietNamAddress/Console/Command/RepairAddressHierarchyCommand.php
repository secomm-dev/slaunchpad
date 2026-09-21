<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Console\Command;

use Magento\Framework\Console\Cli;
use Secomm\VietNamAddress\Model\Import\HierarchyParentBackfill;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * BUG-ZTGGYZ (U1, TASK-FMBBSD) — repair the canonical parent relation of unit snapshots
 * written before the writer synthesised the province edge.
 *
 *   bin/magento secomm:vietnam-address:hierarchy:repair [--scheme=VN_ADMIN_2025]
 *
 * Idempotent: only level-2 units with a NULL parent_code are touched
 * (parent_code = region_code, validated against the scheme's level-1 region unit).
 */
class RepairAddressHierarchyCommand extends Command
{
    public function __construct(
        private readonly HierarchyParentBackfill $backfill,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('secomm:vietnam-address:hierarchy:repair');
        $this->setDescription('Backfill missing province parent_code on level-2 canonical units (idempotent)');
        $this->addOption('scheme', null, InputOption::VALUE_REQUIRED, 'Repair a single scheme instead of all schemes with NULL-parent level-2 rows');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scheme = (string) $input->getOption('scheme');
        if ($scheme !== '') {
            $reports = [$scheme => $this->backfill->backfill($scheme)];
        } else {
            $reports = $this->backfill->backfillAll();
        }

        if ($reports === []) {
            $output->writeln('<info>Nothing to repair — no level-2 unit has a NULL parent_code.</info>');

            return Cli::RETURN_SUCCESS;
        }

        $failure = false;
        foreach ($reports as $schemeCode => $report) {
            $output->writeln(sprintf(
                '<info>%s: backfilled %d, remaining NULL %d, orphan %d</info>',
                $schemeCode,
                $report['backfilled'],
                $report['remainingNull'],
                $report['orphan']
            ));
            if ($report['remainingNull'] > 0) {
                $failure = true;
            }
        }

        if ($failure) {
            $output->writeln('<error>Level-2 units still have a NULL parent_code (orphan regions) — inspect the dataset.</error>');

            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }
}
