<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Console;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Secomm\GiaoHangNhanh\Model\ResourceModel\District as DistrictResource;
use Secomm\GiaoHangNhanh\Model\ResourceModel\Province as ProvinceResource;
use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GenerateRegionCommand
 *
 * @package Secomm\GiaoHangNhanh\Console
 */
class GenerateRegionCommand extends Command
{
    /**
     * @var DistrictResource
     */
    private $districtResource;

    /**
     * @var ProvinceResource
     */
    private $provinceResource;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @param DistrictResource $districtResource
     * @param ProvinceResource $provinceResource
     * @param CommandPoolInterface $commandPool
     * @param string|null $name
     */
    public function __construct(
        DistrictResource $districtResource,
        ProvinceResource $provinceResource,
        CommandPoolInterface $commandPool,
        $name = null
    ) {
        $this->districtResource = $districtResource;
        $this->provinceResource = $provinceResource;
        $this->commandPool = $commandPool;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Generate region data.');
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commandResult = $this->commandPool->get('get_districts')->execute([]);
        $data = SubjectReader::readDistricts($commandResult->get());

        if (!$data) {
            $output->writeln('<error>Generating data was interrupted. Please try again!</error>');
            return Cli::RETURN_FAILURE;
        }

        $provinces = $this->provinceResource->getAll();
        if (empty($provinces)) {
            $output->writeln('<error>No provinces in DB. Please run ghn:province:generate first!</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Generating data. Please wait...</info>');
        $connection = $this->districtResource->getConnection();

        foreach ($data as $item) {
            $provinceId = (int) $item['ProvinceID'];
            $districtId = (int) $item['DistrictID'];

            if (!$this->provinceResource->existsByProvinceId($provinceId)) {
                $output->writeln('<comment>Skipping district ' . $districtId . ' (' . $item['DistrictName'] . '): ProvinceID ' . $provinceId . ' does not exist in DB</comment>');
                continue;
            }

            if (!$this->districtResource->existsByDistrictId($districtId)) {
                $connection->insert(
                    $this->districtResource->getMainTable(),
                    [
                        'district_id' => $districtId,
                        'province_id' => $provinceId,
                        'district_name' => $item['DistrictName']
                    ]
                );
            }
        }

        $output->writeln('<info>Generate data successfully.</info>');
        return Cli::RETURN_SUCCESS;
    }
}
