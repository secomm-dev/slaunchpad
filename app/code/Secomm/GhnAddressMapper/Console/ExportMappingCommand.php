<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping\CollectionFactory;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File;

class ExportMappingCommand extends Command
{
    public function __construct(
        protected CollectionFactory $collectionFactory,
        protected Csv $csv,
        protected File $fileDriver,
        $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Export GHN address mapping data to CSV');
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', 'var/export/ghn_address_mapping.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getOption('output');

        $collection = $this->collectionFactory->create();
        $data = [[
            'country_id',
            'region_id',
            'city_id',
            'ghn_province_id',
            'ghn_district_id',
            'ghn_ward_code'
        ]];

        foreach ($collection as $mapping) {
            $data[] = [
                $mapping->getCountryId(),
                $mapping->getRegionId(),
                $mapping->getCityId(),
                $mapping->getGhnProvinceId(),
                $mapping->getGhnDistrictId(),
                $mapping->getGhnWardCode()
            ];
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            $this->csv->saveData($filePath, $data);
            $output->writeln('<info>Exported ' . (count($data) - 1) . ' mappings to ' . $filePath . '</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>Export failed: ' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }
}
