<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Console\Command;

use Magento\Framework\Console\Cli;
use Secomm\VietNamAddress\Model\Import\VnAddressSchemeImporter;
use Secomm\VietNamAddress\Model\Import\VnImportReport;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TASK-ADT94K (DEC-FEATYA2C0W-003) — install / refresh / rebuild / swap the VN address scheme.
 *
 *   bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_2025 [--dry-run]   # refresh
 *   bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_2025 --rebuild    # clean re-import
 *   bin/magento secomm:vietnam-address:import --scheme VN_ADMIN_PRE_2025 --swap   # switch scheme
 *
 * Scheme values are the canonical versioned identities (VnSchemes catalog). The relative
 * pre-DEC-003 values (vn_current/vn_legacy) are rejected with a pointer to the new codes —
 * CURRENT/LEGACY are status labels, never identities.
 */
class ImportVnAddressSchemeCommand extends Command
{
    public function __construct(
        private readonly VnAddressSchemeImporter $importer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('secomm:vietnam-address:import');
        $this->setDescription(
            'Import/refresh a Vietnam address dataset (vn_current | vn_legacy); '
            . '--swap purges the other scheme first, --dry-run validates only'
        );
        $this->addOption(
            'scheme',
            null,
            InputOption::VALUE_REQUIRED,
            'Dataset scheme: vn_current or vn_legacy'
        );
        $this->addOption(
            'swap',
            null,
            InputOption::VALUE_NONE,
            'Required when switching schemes: purge the installed VN data before importing'
        );
        $this->addOption(
            'rebuild',
            null,
            InputOption::VALUE_NONE,
            'Destructive clean re-import: purge the installed VN runtime data AND this scheme\'s unit snapshot, then import from scratch (new city_ids)'
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Validate the dataset and simulate (no writes)'
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scheme = (string)$input->getOption('scheme');
        if (!VnSchemes::exists($scheme)) {
            $legacy = ['vn_current' => VnSchemes::VN_ADMIN_2025, 'vn_legacy' => VnSchemes::VN_ADMIN_PRE_2025];
            $hint = isset($legacy[$scheme]) ? sprintf(' (the pre-DEC-003 value "%s" is now "%s")', $scheme, $legacy[$scheme]) : '';
            $output->writeln('<error>--scheme must be a canonical scheme code: ' . implode(' | ', array_keys(VnSchemes::catalog())) . $hint . '.</error>');

            return Cli::RETURN_FAILURE;
        }

        $swap = (bool)$input->getOption('swap');
        $rebuild = (bool)$input->getOption('rebuild');
        $dryRun = (bool)$input->getOption('dry-run');

        try {
            $report = $dryRun
                ? $this->importer->dryRun($scheme)
                : $this->importer->import($scheme, $swap, $rebuild);
        } catch (VnImportValidationException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            foreach ($e->getErrors() as $error) {
                $output->writeln('<comment>  - ' . $error . '</comment>');
            }

            return Cli::RETURN_FAILURE;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        $this->printReport($report, $output);

        return $report->hasErrors() ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    private function printReport(VnImportReport $report, OutputInterface $output): void
    {
        $tag = $report->hasErrors() ? 'error' : 'info';
        $output->writeln(sprintf(
            '<%s>VN address scheme "%s" %s%s%s</%s>',
            $tag,
            $report->scheme,
            $report->dryRun ? 'validated (dry run)' : 'import complete',
            $report->swapPerformed ? ' [scheme swapped]' : '',
            $report->rebuildPerformed ? ' [rebuilt from scratch]' : '',
            $tag
        ));

        $output->writeln(sprintf(
            '  installed scheme(s): %s | rows validated: %d region + %d unit',
            $report->installedSchemes === [] ? 'none' : implode(', ', $report->installedSchemes),
            $report->regionRowsValidated,
            $report->unitRowsValidated
        ));
        if ($report->swapPerformed || $report->rebuildPerformed) {
            $output->writeln(sprintf(
                '  purged: %d regions, %d cities, %d membership rows%s',
                $report->purgedRegions,
                $report->purgedCities,
                $report->purgedMembership,
                $report->rebuildPerformed ? sprintf(', %d unit snapshot rows', $report->unitsPurged) : ''
            ));
        }
        if ($report->dryRun && ($report->purgedRegions || $report->purgedCities)) {
            $output->writeln(sprintf(
                '  swap would purge: %d regions, %d cities, %d membership rows',
                $report->purgedRegions,
                $report->purgedCities,
                $report->purgedMembership
            ));
        }

        if (!$report->dryRun) {
            $output->writeln(sprintf(
                '  regions: %d inserted / %d updated | cities: %d inserted / %d updated',
                $report->regionsInserted,
                $report->regionsUpdated,
                $report->citiesInserted,
                $report->citiesUpdated
            ));
            $output->writeln(sprintf(
                '  re-key: regions %d matched / %d missed, cities %d matched / %d missed'
                . ' | stale removed: %d cities / %d regions | orphan claims: %d'
                . ' | unit snapshot: %d | membership: %d | config: %s',
                $report->rekeyRegionMatched,
                $report->rekeyRegionMissed,
                $report->rekeyMatched,
                $report->rekeyMissed,
                $report->staleRemoved,
                $report->staleRegionsRemoved,
                $report->orphanMembershipRemoved,
                $report->unitsSnapshoted,
                $report->membershipRows,
                $report->configUpdated ? 'updated to ' . $report->scheme : 'unchanged'
            ));
        } elseif ($report->rekeyRegionMatched || $report->rekeyRegionMissed
            || $report->rekeyMatched || $report->rekeyMissed
        ) {
            $output->writeln(sprintf(
                '  re-key simulation: regions %d matched / %d missed, cities %d matched / %d missed',
                $report->rekeyRegionMatched,
                $report->rekeyRegionMissed,
                $report->rekeyMatched,
                $report->rekeyMissed
            ));
        }

        foreach ($report->warnings as $warning) {
            $output->writeln('<comment>  warning: ' . $warning . '</comment>');
        }
        foreach ($report->errors as $error) {
            $output->writeln('<error>  ' . $error . '</error>');
        }
    }
}
