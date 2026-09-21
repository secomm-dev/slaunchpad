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
use Secomm\Ghn\Model\Address\Mapping\MappingSuggester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * DEC-FEATFQWEQ3-002 — candidate-generation tooling (legacy matcher, classification A):
 * produces an import-compatible mapping workfile for OFFLINE review. Never approves anything.
 */
class SuggestMappingCommand extends Command
{
    public const COMMAND_NAME = 'secomm:ghn:address:suggest';

    public function __construct(
        private readonly MappingSuggester $suggester,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setDescription('Generate a REVIEW-required mapping workfile + review artifact from deterministic name matching (never auto-approves)')
            ->addOption('scheme', null, InputOption::VALUE_REQUIRED, 'Canonical scheme: VN_ADMIN_2025 or VN_ADMIN_PRE_2025')
            ->addOption('export-dir', null, InputOption::VALUE_REQUIRED, 'Exported master dataset dir (offline authoring; default: read synced DB master data)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Workfile path (default: <var>/secomm_ghn/suggest/<scheme>_workfile.csv)')
            ->addOption('review-output', null, InputOption::VALUE_REQUIRED, 'Review artifact path (default: workfile dir /GHN_ADDRESS_MAPPING_REVIEW.csv)');
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

        $outputFile = (string) $input->getOption('output');
        if ($outputFile === '') {
            $outputFile = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath()
                . sprintf('secomm_ghn/suggest/%s_workfile.csv', $scheme);
        }

        $reviewFile = (string) $input->getOption('review-output');
        if ($reviewFile === '') {
            $reviewFile = dirname($outputFile) . '/GHN_ADDRESS_MAPPING_REVIEW_' . $scheme . '.csv';
        }

        $exportDir = (string) $input->getOption('export-dir');
        $report = $exportDir !== ''
            ? $this->suggester->suggestFromExport($scheme, $exportDir, $outputFile, $reviewFile)
            : $this->suggester->suggest($scheme, $outputFile, $reviewFile);

        $output->writeln(sprintf('<info>Workfile: %s</info>', $report['output']));
        $output->writeln(sprintf('Review artifact: %s (%d rows)', $report['review_output'], $report['review_rows']));
        $output->writeln(sprintf(
            'Rows: %d · REVIEW_REQUIRED %d · AMBIGUOUS %d · UNRESOLVED %d',
            $report['total'],
            $report['review_required'],
            $report['ambiguous'],
            $report['unresolved']
        ));
        $output->writeln('Review offline, set APPROVED (with mapping_method) and import via secomm:ghn:address:import.');

        return Cli::RETURN_SUCCESS;
    }
}
