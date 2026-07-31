<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Console;

use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Secomm\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\ResourceConnection;
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
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * GeneratingRegionData constructor.
     * @param ResourceConnection $resourceConnection
     * @param RegionFactory $regionFactory
     * @param CommandPoolInterface $commandPool
     * @param string|null $name
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        RegionFactory $regionFactory,
        CommandPoolInterface $commandPool,
        $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->regionFactory = $regionFactory;
        $this->commandPool = $commandPool;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Generate region data.');
        parent::configure();
    }

    public function test(){
         $commandResult = $this->commandPool->get('get_districts')->execute([]);
        $data = SubjectReader::readDistricts($commandResult->get());
}
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commandResult = $this->commandPool->get('get_districts')->execute([]);
        $data = SubjectReader::readDistricts($commandResult->get());

        if ($data) {
            $listProvinces = $this->getListProvinces();
            if (empty($listProvinces)) {
                $output->writeln('<error>List Provinces: empty</error>');
            }
            $output->writeln('<info>Generating data. Please wait...</info>');
            foreach ($data as $item) {
                $provinceId = $item['ProvinceID'];
                $districtId = $item['DistrictID'];
                $region = $this->regionFactory->create()
                    ->loadByCode($provinceId, 'VN');

                $this->insertData(
                    'secomm_giaohangnhanh_district',
                    [
                        'district_id' => $districtId,
                        'province_id' => $provinceId,
                        'district_name' => $item['DistrictName']
                    ],
                    ['col' => 'district_id', 'val' => $districtId]
                );

                if (!$region->getId()) {
                    $provinceName = $this->getProvinceNameById($listProvinces, $provinceId);
                    if (!empty($provinceName)) {
                        $this->insertData(
                            'directory_country_region',
                            [
                                'country_id' => 'VN',
                                'code' => $provinceId,
                                'default_name' => $provinceName
                            ]
                        );
                    } else {
                        $output->writeln('<error>ProvinceId: '.$provinceId.' - ProvinceName: empty</error>');
                    }
                }
            }
            $output->writeln('<info>Generate data successfully.</info>');
            return 0;
        } else {
            $output->writeln('<error>Generating data was interrupted. Please try again!</error>');
            return 1;
        }
    }

    /**
     * @param string $tableName
     * @param array $data
     * @param array $pairOfColAndVal
     */
    private function insertData($tableName, $data, $pairOfColAndVal = [])
    {
        if (!$this->checkRecordExist($tableName, $pairOfColAndVal)) {
            $this->resourceConnection->getConnection()->insert(
                $this->resourceConnection->getTableName($tableName),
                $data
            );
        }
    }


    /**
     * @param string $tableName
     * @param array $pairOfColAndVal
     * @return bool
     */
    private function checkRecordExist($tableName, $pairOfColAndVal = [])
    {
        $checkingFlag = false;

        if ($pairOfColAndVal) {
            $connection = $this->resourceConnection->getConnection();
            $sql = $connection->select()->from(
                ['districtTable' => $this->resourceConnection->getTableName($tableName)],
                $pairOfColAndVal['col']
            )->where($pairOfColAndVal['col'] . ' = ?', $pairOfColAndVal['val']);

            $rows = $connection->fetchAll($sql);

            if (count($rows)) {
                $checkingFlag = true;
            }
        }

        return $checkingFlag;
    }

    /**
     * Get list provinces
     * @return array
     */
    private function getListProvinces()
    {
        $commandResult = $this->commandPool->get('get_provinces')->execute([]);
        $provinces = SubjectReader::readProvinces($commandResult->get());

        return $provinces;
    }

    /**
     * Get province name by ID
     *
     * @param array $provinces
     * @param int $provinceId
     * @return string
     */
    private function getProvinceNameById($provinces, $provinceId)
    {
        if (empty($provinces)) {
            return "";
        }

        $provinceName = "";
        foreach($provinces as $item) {
            if($item["ProvinceID"] == $provinceId) {
                $provinceName = $item["ProvinceName"];

                return $provinceName;
            }
        }

        return $provinceName;
    }
}
