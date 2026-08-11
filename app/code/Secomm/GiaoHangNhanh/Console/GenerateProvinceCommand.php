<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Console;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Secomm\GiaoHangNhanh\Model\ResourceModel\Province as ProvinceResource;
use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateProvinceCommand extends Command
{
    /**
     * @var ProvinceResource
     */
    private $provinceResource;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @param ProvinceResource $provinceResource
     * @param CommandPoolInterface $commandPool
     * @param string|null $name
     */
    public function __construct(
        ProvinceResource $provinceResource,
        CommandPoolInterface $commandPool,
        $name = null
    ) {
        $this->provinceResource = $provinceResource;
        $this->commandPool = $commandPool;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Generate GHN province data from API');
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
        $commandResult = $this->commandPool->get('get_provinces')->execute([]);
        $data = SubjectReader::readProvinces($commandResult->get());

        if (!$data) {
            $output->writeln('<error>No province data returned from API</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Generating province data. Please wait...</info>');
        $connection = $this->provinceResource->getConnection();

        foreach ($data as $item) {
            $provinceId = (int) $item['ProvinceID'];
            $provinceName = $item['ProvinceName'] ?? '';
            $countryId = $item['CountryID'] ?? 'VN';
            $code = $item['Code'] ?? (string) $provinceId;

            if (!$this->provinceResource->existsByProvinceId($provinceId)) {
                $connection->insert(
                    $this->provinceResource->getMainTable(),
                    [
                        'province_id' => $provinceId,
                        'country_id' => $countryId,
                        'code' => $code,
                        'province_name' => $provinceName
                    ]
                );
            }
        }

        $output->writeln('<info>Generate province data successfully.</info>');
        return Cli::RETURN_SUCCESS;
    }
}
