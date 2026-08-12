<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Secomm\GhnAddressMapper\Model\ResourceModel\LocationMapping as LocationMappingResource;

class ValidateMappingCommand extends Command
{
    public function __construct(
        protected ResourceConnection $resourceConnection,
        protected LocationMappingResource $locationMappingResource,
        $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Validate GHN address mapping data for integrity and consistency');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(LocationMappingResource::TABLE_NAME);
        $issues = 0;

        $output->writeln('<info>=== GHN Address Mapping Validation Report ===</info>');
        $output->writeln('');

        // Check ambiguous priority: multiple active (status=1) mappings for the same city_id
        // with the same highest priority — resolver cannot deterministically pick one.
        $output->writeln('<info>Checking for ambiguous priority conflicts...</info>');
        $ambiguousSelect = $connection->select()
            ->from(['m' => $table], ['city_id', 'priority', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('status = 1')
            ->where('city_id IS NOT NULL')
            ->group(['city_id', 'priority'])
            ->having('cnt > 1');
        $ambiguous = $connection->fetchAll($ambiguousSelect);
        if (count($ambiguous) > 0) {
            $output->writeln('<error>Found ' . count($ambiguous) . ' city_id(s) with multiple active mappings at the same priority:</error>');
            foreach ($ambiguous as $row) {
                $output->writeln(sprintf('  city_id %s at priority %d (x%d active rows)', $row['city_id'], $row['priority'], $row['cnt']));
                $issues++;
            }
            $output->writeln('<comment>  Set distinct priorities to control which GHN address is resolved at checkout.</comment>');
        } else {
            $output->writeln('<info>  No ambiguous priority conflicts found.</info>');
        }
        $output->writeln('');

        // Check mappings with unresolved city_id (NULL)
        $nullCityCount = (int)$connection->fetchOne(
            $connection->select()->from($table, [new \Zend_Db_Expr('COUNT(*)')])->where('city_id IS NULL')
        );
        if ($nullCityCount > 0) {
            $output->writeln('<error>Found ' . $nullCityCount . ' mappings with unresolved city_id (NULL). Please fix manually.</error>');
            $issues += $nullCityCount;
        }
        $output->writeln('');

        // Check City ID references
        $output->writeln('<info>Checking City ID references...</info>');
        $citySelect = $connection->select()
            ->from(['m' => $table], ['m.entity_id', 'm.city_id'])
            ->joinLeft(['c' => $this->resourceConnection->getTableName('directory_region_city')],
                'm.city_id = c.city_id', [])
            ->where('m.city_id IS NOT NULL AND c.city_id IS NULL');
        $invalidCities = $connection->fetchAll($citySelect);
        if (count($invalidCities) > 0) {
            $output->writeln('<error>Found ' . count($invalidCities) . ' mappings with invalid City IDs:</error>');
            foreach ($invalidCities as $row) {
                $output->writeln(sprintf('  Entity ID %d: City ID %s does not exist', $row['entity_id'], $row['city_id']));
                $issues++;
            }
        } else {
            $output->writeln('<info>  All City ID references are valid.</info>');
        }
        $output->writeln('');

        // Check GHN Province IDs
        $output->writeln('<info>Checking GHN Province IDs...</info>');
        $provinceSelect = $connection->select()
            ->from(['m' => $table], ['m.entity_id', 'm.ghn_province_id'])
            ->joinLeft(['p' => $this->resourceConnection->getTableName('secomm_giaohangnhanh_province')],
                'm.ghn_province_id = p.province_id', [])
            ->where('p.province_id IS NULL');
        $invalidProvinces = $connection->fetchAll($provinceSelect);
        if (count($invalidProvinces) > 0) {
            $output->writeln('<error>Found ' . count($invalidProvinces) . ' mappings with invalid GHN Province IDs:</error>');
            foreach ($invalidProvinces as $row) {
                $output->writeln(sprintf('  Entity ID %d: GHN Province ID %s does not exist', $row['entity_id'], $row['ghn_province_id']));
                $issues++;
            }
        } else {
            $output->writeln('<info>  All GHN Province IDs are valid.</info>');
        }
        $output->writeln('');

        // Check GHN District IDs
        $output->writeln('<info>Checking GHN District IDs...</info>');
        $districtSelect = $connection->select()
            ->from(['m' => $table], ['m.entity_id', 'm.ghn_district_id', 'm.ghn_province_id'])
            ->joinLeft(['d' => $this->resourceConnection->getTableName('secomm_giaohangnhanh_district')],
                'm.ghn_district_id = d.district_id', ['d.province_id as actual_province_id'])
            ->where('d.district_id IS NULL OR d.province_id != m.ghn_province_id');
        $invalidDistricts = $connection->fetchAll($districtSelect);
        if (count($invalidDistricts) > 0) {
            $output->writeln('<error>Found ' . count($invalidDistricts) . ' mappings with invalid GHN District IDs:</error>');
            foreach ($invalidDistricts as $row) {
                $msg = sprintf('  Entity ID %d: GHN District ID %s', $row['entity_id'], $row['ghn_district_id']);
                if ($row['actual_province_id'] && $row['actual_province_id'] != $row['ghn_province_id']) {
                    $msg .= sprintf(' belongs to Province %s, not %s', $row['actual_province_id'], $row['ghn_province_id']);
                } else {
                    $msg .= ' does not exist';
                }
                $output->writeln($msg);
                $issues++;
            }
        } else {
            $output->writeln('<info>  All GHN District IDs are valid.</info>');
        }
        $output->writeln('');

        // Check GHN Ward Codes
        $output->writeln('<info>Checking GHN Ward...</info>');
        $wardSelect = $connection->select()
            ->from(['m' => $table], ['m.entity_id', 'm.ghn_ward_code', 'm.ghn_district_id'])
            ->joinLeft(['w' => $this->resourceConnection->getTableName('secomm_giaohangnhanh_ward')],
                'm.ghn_ward_code = w.ward_code', [])
            ->joinLeft(['wd' => $this->resourceConnection->getTableName('secomm_giaohangnhanh_district')],
                'w.district_id = wd.entity_id', ['wd.district_id as actual_district_id'])
            ->where('w.ward_code IS NULL OR wd.district_id IS NULL OR wd.district_id != m.ghn_district_id');
        $invalidWards = $connection->fetchAll($wardSelect);
        if (count($invalidWards) > 0) {
            $output->writeln('<error>Found ' . count($invalidWards) . ' mappings with invalid GHN Ward:</error>');
            foreach ($invalidWards as $row) {
                $msg = sprintf('  Entity ID %d: GHN Ward %s', $row['entity_id'], $row['ghn_ward_code']);
                if ($row['actual_district_id'] && $row['actual_district_id'] != $row['ghn_district_id']) {
                    $msg .= sprintf(' belongs to District %s, not %s', $row['actual_district_id'], $row['ghn_district_id']);
                } else {
                    $msg .= ' does not exist';
                }
                $output->writeln($msg);
                $issues++;
            }
        } else {
            $output->writeln('<info>  All GHN Ward are valid.</info>');
        }
        $output->writeln('');

        // Check Region references
        $output->writeln('<info>Checking Region references...</info>');
        $regionSelect = $connection->select()
            ->from(['m' => $table], ['m.entity_id', 'm.region_id'])
            ->joinLeft(['r' => $this->resourceConnection->getTableName('directory_country_region')],
                'm.region_id = r.region_id', [])
            ->where('r.region_id IS NULL');
        $invalidRegions = $connection->fetchAll($regionSelect);
        if (count($invalidRegions) > 0) {
            $output->writeln('<error>Found ' . count($invalidRegions) . ' mappings with invalid Region IDs:</error>');
            foreach ($invalidRegions as $row) {
                $output->writeln(sprintf('  Entity ID %d: Region ID %s does not exist', $row['entity_id'], $row['region_id']));
                $issues++;
            }
        } else {
            $output->writeln('<info>  All Region references are valid.</info>');
        }
        $output->writeln('');

        // Check mappings with status=0
        $disabledCount = $connection->fetchOne(
            $connection->select()->from($table, [new \Zend_Db_Expr('COUNT(*)')])->where('status = 0')
        );
        if ($disabledCount > 0) {
            $output->writeln('<comment>' . $disabledCount . ' disabled mappings found.</comment>');
        }

        if ($issues === 0) {
            $output->writeln('<info>All validations passed. No issues found.</info>');
        } else {
            $output->writeln('<error>Total issues found: ' . $issues . '</error>');
        }

        return $issues === 0 ? \Magento\Framework\Console\Cli::RETURN_SUCCESS : \Magento\Framework\Console\Cli::RETURN_FAILURE;
    }
}
