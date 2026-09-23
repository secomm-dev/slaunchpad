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
use Secomm\Ghn\Model\Address\Import\MappingImporter;
use Secomm\Ghn\Model\Address\Import\MasterDataImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SPEC-TASK-TBM30R §2/§8 — import address datasets: default (no --dir) bootstraps the module's
 * bundled reviewed dataset; --dir imports an exported/reviewed dataset. Master import and mapping
 * import are separate operations run in one command flow, each fail-loud and UPSERT-based.
 */
class ImportAddressCommand extends Command
{
    public const COMMAND_NAME = 'secomm:ghn:address:import';

    public function __construct(
        private readonly MasterDataImporter $masterImporter,
        private readonly MappingImporter $mappingImporter,
        private readonly \Secomm\Ghn\Model\Address\Dataset\DatasetPaths $datasetPaths
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setDescription('Import GHN master + reviewed mapping CSV datasets (default dir = module bundled data/ = bootstrap)')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Dataset directory (default: bundled module data/)')
            ->addOption('scheme', null, InputOption::VALUE_REQUIRED, 'Canonical scheme (VN_ADMIN_2025 or VN_ADMIN_PRE_2025; default: both pairs)')
            ->addOption('master-only', null, InputOption::VALUE_NONE, 'Import master data only')
            ->addOption('mapping-only', null, InputOption::VALUE_NONE, 'Import reviewed mapping only')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate + report without writing');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = (string) ($input->getOption('dir') ?: $this->datasetPaths->bundledDir());
        $scheme = (string) $input->getOption('scheme');
        $canonicalScheme = $scheme !== '' ? $scheme : null;
        $dryRun = (bool) $input->getOption('dry-run');
        $masterOnly = (bool) $input->getOption('master-only');
        $mappingOnly = (bool) $input->getOption('mapping-only');
        if ($masterOnly && $mappingOnly) {
            throw new LocalizedException(__('Options --master-only and --mapping-only are mutually exclusive.'));
        }

        if (!$mappingOnly) {
            $masterReport = $this->masterImporter->import($dir, $canonicalScheme === null ? null : $this->canonicalToGhn($canonicalScheme), null, $dryRun);
            foreach ($masterReport['schemes'] as $report) {
                $output->writeln(sprintf(
                    '[master] %s: %d records · affected %d · disabled %d%s',
                    $report['scheme'],
                    $report['records'],
                    $report['affected'],
                    $report['disabled'],
                    $report['dry_run'] ? ' (dry run)' : ''
                ));
            }
        }

        if (!$masterOnly) {
            $mappingReport = $this->mappingImporter->import($dir, $canonicalScheme, null, $dryRun);
            foreach ($mappingReport['schemes'] as $canonical => $report) {
                $output->writeln(sprintf(
                    '[mapping] %s: %d rows · APPROVED activated %d · skipped (review %d / unresolved %d / ambiguous %d)%s',
                    $canonical,
                    $report['total_rows'],
                    $report['approved'],
                    $report['skipped_review_required'],
                    $report['skipped_unresolved'],
                    $report['skipped_ambiguous'],
                    $mappingReport['dry_run'] ? ' (dry run)' : ''
                ));
            }
        }

        $output->writeln(sprintf('Dataset dir: %s', $dir));

        return Cli::RETURN_SUCCESS;
    }

    private function canonicalToGhn(string $canonicalScheme): ?string
    {
        return $this->datasetPaths->schemePairs()[$canonicalScheme]
            ?? throw new LocalizedException(
                __('Unknown canonical scheme %1 — expected one of: %2.', $canonicalScheme, implode(', ', array_keys($this->datasetPaths->schemePairs())))
            );
    }
}
