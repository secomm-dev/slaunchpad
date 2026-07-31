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

use Secomm\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateWardCommand extends Command
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * GeneratingRegionData constructor.
     * @param ResourceConnection $resourceConnection
     * @param CommandPoolInterface $commandPool
     * @param string|null $name
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        CommandPoolInterface $commandPool,
        $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->commandPool = $commandPool;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Generate ward data.');
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
        $data = $this->getListDistrict();

        if (count($data) > 0) {
            $output->writeln('<info>Generating data. Please wait...</info>');
            foreach ($data as $item) {
                $districtId = $item['district_id'];
                $entityId = $item['entity_id'];
                $wards = $this->getWardsByDistrictId($districtId);
                if (count($wards) > 0) {
                    foreach ($wards as $ward) {
                        $wardCode = $ward['WardCode'];
                        $this->insertData(
                            'secomm_giaohangnhanh_ward',
                            [
                                'ward_code' => $wardCode,
                                'district_id' => $entityId,
                                'ward_name' => $ward['WardName']
                            ],
                            ['col' => 'ward_code', 'val' => $wardCode]
                        );
                    }
                    $output->writeln('<info>Generate data ward by districtId: '.$item['district_id'].' successfully.</info>');
                } else {
                    $output->writeln('<error>DistrictId: '.$item['district_id'].' not have wards</error>');
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
                ['wardTable' => $this->resourceConnection->getTableName($tableName)],
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
     * Get wards by ID
     *
     * @param int $districtId
     * @return array
     */
    private function getWardsByDistrictId($districtId)
    {
        $commandResult = $this->commandPool->get('get_wards')->execute([
            'district_id' => (int)$districtId,
        ]);

        $result = $commandResult->get();
        if (!isset($result['wards'])) {
            return [];
        }

        return $result['wards'];
    }

    /**
     * Get list district
     * @return array|null
     */
    private function getListDistrict()
    {
        $connection = $this->resourceConnection->getConnection();
        $sql = $connection->select()->from(
            ['districtTable' => $this->resourceConnection->getTableName('secomm_giaohangnhanh_district')],
        );

        $rows = $connection->fetchAll($sql);

        if (count($rows) > 0) {
            return $rows;
        }

        return null;
    }
}
