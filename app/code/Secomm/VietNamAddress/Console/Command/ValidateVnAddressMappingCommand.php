<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Console\Command;

use Magento\Framework\Console\Cli;
use Secomm\VietNamAddress\Model\Import\VnMappingImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TASK-J9AVGK — validate imported mapping data (or a candidate file via --file):
 * orphan codes, same-scheme edges, bad relation types, duplicate edges (errors) and the
 * reverse-ambiguity report (informational warnings — expected for MERGED_INTO).
 *
 *   bin/magento secomm:vietnam-address:validate-mapping [--file <path>]
 */
class ValidateVnAddressMappingCommand extends Command
{
    private const OPT_FILE = 'file';

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
        $this->setName('secomm:vietnam-address:validate-mapping');
        $this->setDescription('Validate Vietnam administrative mapping data (DB, or a file via --file)');
        $this->addOption(self::OPT_FILE, null, InputOption::VALUE_REQUIRED, 'Validate a mapping CSV file instead of the DB');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getOption(self::OPT_FILE);
        if ($path !== null && !is_file((string)$path) && is_file(BP . '/' . ltrim((string)$path, '/'))) {
            $path = BP . '/' . ltrim((string)$path, '/');
        }

        ['errors' => $errors, 'warnings' => $warnings] = $this->importer->validate($path === null ? null : (string)$path);

        $output->writeln(sprintf('<info>Mapping validation: %d error(s), %d warning(s).</info>', count($errors), count($warnings)));
        foreach ($errors as $error) {
            $output->writeln('<error>  ' . $error . '</error>');
        }
        foreach ($warnings as $warning) {
            $output->writeln('<comment>  warning: ' . $warning . '</comment>');
        }

        return $errors === [] ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
    }
}
