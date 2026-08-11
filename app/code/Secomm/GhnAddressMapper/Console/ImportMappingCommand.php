<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\File\Csv;
use Secomm\GhnAddressMapper\Model\Import\MappingImporter;
use Psr\Log\LoggerInterface;

class ImportMappingCommand extends Command
{
    public function __construct(
        protected Csv $csv,
        protected MappingImporter $mappingImporter,
        protected LoggerInterface $logger,
        $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Import GHN address mapping from CSV file');
        $this->addArgument('file', InputArgument::REQUIRED, 'CSV file path');
        $this->addOption('update', 'u', InputOption::VALUE_NONE, 'Update existing records');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $updateExisting = (bool)$input->getOption('update');

        if (!file_exists($filePath)) {
            $output->writeln('<error>File not found: ' . $filePath . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        $csvData = $this->csv->getData($filePath);
        if (empty($csvData) || count($csvData) < 2) {
            $output->writeln('<error>CSV file is empty or has no data rows.</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        $result = $this->mappingImporter->importAll($csvData, $updateExisting);

        $output->writeln('');
        $output->writeln('<info>=== Import Results ===</info>');
        $output->writeln('Imported: ' . $result['imported']);
        $output->writeln('Updated:  ' . $result['updated']);
        $output->writeln('Skipped:  ' . $result['skipped']);
        $output->writeln('Failed:   ' . $result['failed']);

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $output->writeln('<error>' . $err . '</error>');
            }
        }

        return $result['failed'] === 0 ? \Magento\Framework\Console\Cli::RETURN_SUCCESS : \Magento\Framework\Console\Cli::RETURN_FAILURE;
    }
}
