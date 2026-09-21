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
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\Ghn\Model\Address\Mapping\MappingAuditor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * SPEC-FEAT-FQWEQ3 §8 / AC-ADDR-006 — mapping audit: mapped/unmapped/ambiguous/invalid/
 * disabled_provider_unit + coverage % per level. Nothing is auto-approved here.
 */
class AuditAddressCommand extends Command
{
    public const COMMAND_NAME = 'secomm:ghn:address:audit';

    private const FORMAT_TABLE = 'table';
    private const FORMAT_JSON = 'json';

    public function __construct(
        private readonly MappingAuditor $auditor,
        private readonly Json $serializer
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setDescription('Audit canonical→GHN mapping coverage for one scheme pair')
            ->addOption(
                'scheme',
                null,
                InputOption::VALUE_REQUIRED,
                'Canonical scheme: VN_ADMIN_2025 or VN_ADMIN_PRE_2025'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('Output format: %s or %s', self::FORMAT_TABLE, self::FORMAT_JSON),
                self::FORMAT_TABLE
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

        $format = (string) $input->getOption('format');
        if ($format !== self::FORMAT_TABLE && $format !== self::FORMAT_JSON) {
            throw new LocalizedException(__('Option --format must be "%1" or "%2".', self::FORMAT_TABLE, self::FORMAT_JSON));
        }

        $report = $this->auditor->audit($scheme);

        if ($format === self::FORMAT_JSON) {
            $output->writeln($this->serializer->serialize($report));

            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf('<info>Canonical scheme: %s → GHN scheme: %s</info>', $report['secomm_scheme_code'], $report['ghn_scheme_code']));
        $output->writeln(sprintf(
            'Mapped: %d / %d (%s%%) · unmapped: %d · ambiguous: %d · invalid: %d · stale: %d · duplicate: %d · dangling: %d · disabled provider unit: %d',
            $report['mapped'],
            $report['total_canonical'],
            $report['coverage_percent'],
            $report['unmapped'],
            $report['ambiguous'],
            $report['invalid'],
            $report['stale'],
            $report['duplicate'],
            $report['dangling'],
            $report['disabled_provider_unit']
        ));
        $output->writeln(sprintf(
            'Production ready: %s',
            $report['production_ready'] ? '<info>YES</info>' : '<comment>NO — do not treat this dataset as production-ready</comment>'
        ));

        foreach ($report['by_level'] as $level => $stats) {
            $output->writeln(sprintf(
                '  level %d: mapped %d/%d (%s%%) · unmapped %d · ambiguous %d',
                $level,
                $stats['mapped'] ?? 0,
                $stats['total'] ?? 0,
                $stats['coverage_percent'],
                $stats['unmapped'] ?? 0,
                $stats['ambiguous'] ?? 0
            ));
        }

        foreach (['unmapped_codes', 'invalid_codes', 'stale_codes', 'dangling_codes', 'disabled_provider_codes'] as $listKey) {
            if ($report[$listKey] === []) {
                continue;
            }
            $output->writeln(sprintf('%s (%d): %s', $listKey, count($report[$listKey]), implode(', ', array_slice($report[$listKey], 0, 25))));
            if (count($report[$listKey]) > 25) {
                $output->writeln(sprintf('  … and %d more — use --format=%s for the full list', count($report[$listKey]) - 25, self::FORMAT_JSON));
            }
        }

        foreach ($report['ambiguous_codes'] as $unitCode => $candidates) {
            $output->writeln(sprintf('ambiguous %s: %s', $unitCode, implode(' | ', $candidates)));
        }

        return Cli::RETURN_SUCCESS;
    }
}
