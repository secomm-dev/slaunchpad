<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */

namespace Secomm\GiaoHangNhanh\Console;

use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\CommandPoolInterface;
use Secomm\GiaoHangNhanh\Model\ResourceModel\Ward as WardResource;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateWardCommand extends Command
{
    /**
     * @var WardResource
     */
    private $wardResource;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * GeneratingRegionData constructor.
     * @param WardResource $wardResource
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface|null $logger
     * @param string|null $name
     */
    public function __construct(
        WardResource         $wardResource,
        CommandPoolInterface $commandPool,
        $name = null,
        ?LoggerInterface     $logger = null
    ) {
        $this->wardResource = $wardResource;
        $this->commandPool = $commandPool;
        $this->logger = $logger ?? \Magento\Framework\App\ObjectManager::getInstance()->get(LoggerInterface::class);
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
        $data = $this->wardResource->getDistricts();
        $totalDistricts = count($data);

        if ($totalDistricts > 0) {
            $output->writeln('<info>Generating ward data for ' . $totalDistricts . ' districts. Please wait...</info>');
            $this->logger->info('[GHN Ward Generate] Started generating ward data for ' . $totalDistricts . ' districts.');

            $current = 0;
            $totalInserted = 0;
            $totalSkipped = 0;

            foreach ($data as $item) {
                $current++;
                $districtId = (int) $item['district_id'];
                $districtName = $item['district_name'] ?? '';
                $entityId = (int) $item['entity_id'];

                if ($this->wardResource->hasWardsForDistrict($entityId)) {
                    continue;
                }

                $wards = $this->getWardsByDistrictId($districtId);

                if (count($wards) > 0) {
                    $rowsToInsert = [];
                    foreach ($wards as $ward) {
                        $wardCode = (int) $ward['WardCode'];
                        $rowsToInsert[] = [
                            'ward_code' => $wardCode,
                            'district_id' => $entityId,
                            'ward_name' => $ward['WardName']
                        ];
                    }
                    $this->wardResource->insertBatch($rowsToInsert);
                    $totalInserted += count($rowsToInsert);

                    if ($current % 25 === 0 || $current === $totalDistricts) {
                        $msg = sprintf(
                            '[%d/%d] Progress: Processed district %d (%s) - Total wards generated so far: %d',
                            $current,
                            $totalDistricts,
                            $districtId,
                            $districtName,
                            $totalInserted
                        );
                        $output->writeln('<info>' . $msg . '</info>');
                        $this->logger->info('[GHN Ward Generate] ' . $msg);
                    }
                } else {
                    $msg = sprintf('[%d/%d] District ID: %d (%s) -> No wards returned from GHN API', $current, $totalDistricts, $districtId, $districtName);
                    $output->writeln('<comment>' . $msg . '</comment>');
                    $this->logger->info('[GHN Ward Generate] ' . $msg);
                }
                gc_collect_cycles();
            }

            $summary = sprintf('FINISHED: Processed %d districts. Total Wards Inserted: %d, Skipped: %d', $totalDistricts, $totalInserted, $totalSkipped);
            $output->writeln('<info>' . $summary . '</info>');
            $this->logger->info('[GHN Ward Generate] ' . $summary);
        } else {
            $output->writeln('<error>No districts found in database. Please run ghn:region:generate first!</error>');
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
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
}
