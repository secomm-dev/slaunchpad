<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Secomm\Ghn\Model\Address\Export\MasterDataExporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SPEC-TASK-TBM30R §4 — deterministic GHN master-data export (CLI preferred operational path).
 */
class ExportAddressCommand extends Command
{
    public const COMMAND_NAME = 'secomm:ghn:address:export';

    public function __construct(
        private readonly MasterDataExporter $exporter,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setDescription('Export GHN administrative master data into deterministic CSV files + manifest')
            ->addOption('scheme', null, InputOption::VALUE_REQUIRED, 'GHN scheme (default: both approved schemes)')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Target directory (default: <var>/secomm_ghn/export)')
            // NOTE: deliberately NOT "--version" — Symfony Console reserves that name app-wide.
            ->addOption('dataset-version', null, InputOption::VALUE_REQUIRED, 'Dataset version label (default: Y.m.d of export date)');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = (string) ($input->getOption('dir') ?: $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath() . 'secomm_ghn/export');

        $report = $this->exporter->export(
            ($scheme = (string) $input->getOption('scheme')) !== '' ? $scheme : null,
            $dir,
            ($version = (string) $input->getOption('dataset-version')) !== '' ? $version : null
        );

        $output->writeln(sprintf('<info>Dataset version: %s</info>', $report['dataset_version']));
        foreach ($report['files'] as $scheme => $stats) {
            $output->writeln(sprintf('  %s: %d units', $scheme, $stats['record_count']));
        }
        $output->writeln(sprintf('Exported to: %s (+ manifest.json)', $report['dir']));

        return Cli::RETURN_SUCCESS;
    }
}
