<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Console\Command;

use Magento\Framework\Console\Cli;
use Secomm\VietNamAddress\Model\Import\VnImportValidationException;
use Secomm\VietNamAddress\Model\Import\VnMappingImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TASK-J9AVGK — import the administrative mapping dataset (directed scheme-pair edges).
 *
 *   bin/magento secomm:vietnam-address:import-mapping <file> [--dry-run]
 */
class ImportVnAddressMappingCommand extends Command
{
    private const ARG_FILE = 'file';

    public function __construct(
        private readonly VnMappingImporter $importer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('secomm:vietnam-address:import-mapping');
        $this->setDescription('Import a Vietnam administrative mapping CSV (source_scheme,source_code,target_scheme,target_code,relation_type)');
        $this->addArgument(self::ARG_FILE, InputArgument::REQUIRED, 'Path to the mapping CSV (relative to Magento root or absolute)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate only (no writes)');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getArgument(self::ARG_FILE);
        if (!is_file($path) && is_file(BP . '/' . ltrim($path, '/'))) {
            $path = BP . '/' . ltrim($path, '/');
        }

        try {
            $report = $this->importer->import($path, (bool)$input->getOption('dry-run'));
        } catch (VnImportValidationException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            foreach ($e->getErrors() as $error) {
                $output->writeln('<comment>  - ' . $error . '</comment>');
            }

            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Mapping %s: %d row(s) validated.</info>',
            $input->getOption('dry-run') ? 'validated (dry run)' : 'import complete',
            $report['rows_validated']
        ));
        foreach ($report['warnings'] as $warning) {
            $output->writeln('<comment>  warning: ' . $warning . '</comment>');
        }

        return Cli::RETURN_SUCCESS;
    }
}
