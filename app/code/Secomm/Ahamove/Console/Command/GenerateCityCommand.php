<?php

namespace Secomm\Ahamove\Console\Command;

use Secomm\Ahamove\Helper\Connection;
use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Config\Source\ApiRequest\Status;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class GenerateCityCommand extends Command
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param string|null $name
     */
    public function __construct(
        Connection $connection,
        ?string    $name = null
    )
    {
        $this->connection = $connection;
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('ahamove:generate:city');
        $this->setDescription('Sync data City from Ahamove');
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $response = $this->connection->getDataFromApi(Config::URL_AHAMOVE_CITY, "Sync data City from Ahamove");
        if ($response->status == Status::STATUS_CODE_SUCCESS) {
            $data = $response->content;
            $output->writeln('<info>Generating data. Please wait...</info>');
            if ($data) {
                foreach ($data as $item) {
                    $this->connection->insertData(
                        'ahamove_city',
                        [
                            'city_id' => $item['_id'],
                            'country_id' => $item['country_id'],
                            'name' => $item['name'],
                            'name_vi_vn' => $item['name_vi_vn'],
                            'level' => $item['level']
                        ],
                        ['col' => 'city_id', 'val' => $item['_id']]
                    );
                    if (isset($item['_id']) && !empty($item['_id'])) {
                        $cityDetailsUrl = Config::URL_AHAMOVE_CITY_DETAILS . '?city_id=' . $item['_id'];
                        $cityDetails = $this->connection->getDataFromApi(
                            $cityDetailsUrl,
                            "Sync data City details from Ahamove"
                        );
                        if ($cityDetails->status == Status::STATUS_CODE_SUCCESS) {
                            $dataDetails = $cityDetails->content;
                            $this->connection->insertData(
                                'ahamove_city_detail',
                                [
                                    'city_id' => $item['_id'],
                                    'name' => $dataDetails['name'],
                                    'name_vi_vn' => $dataDetails['name_vi_vn'],
                                    'country_id' => $dataDetails['country_id'],
                                    'location' => $dataDetails['location'],
                                    'contract_number' => $dataDetails['contract_number'] ?? null,
                                    'merchant_contract_number' => $dataDetails['merchant_contract_number'] ?? null,
                                    'animated_url' => $dataDetails['animated_url'] ?? null,
                                    'same_district_delivery' => $dataDetails['same_district_delivery'] ?? null,
                                    'level' => $dataDetails['level'],
                                    'level_vn' => $dataDetails['level_vn'] ?? null,
                                    'area_id' => $dataDetails['area_id'] ?? null,
                                    'service_city_id' => $dataDetails['service_city_id'] ?? null,
                                    'public_service' => $dataDetails['public_service'] ?? null
                                ],
                                ['col' => 'city_id', 'val' => $dataDetails['_id']]
                            );
                        }
                    }
                }
            }

            $output->writeln('<info>Generate data successfully.</info>');
        } else {
            $output->writeln('<error>Generating data was interrupted. Please try again!</error>');
        }
    }
}
