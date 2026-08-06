<?php

namespace Secomm\Ahamove\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Secomm\Ahamove\Helper\Data as AhamoveHelper;
use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Connect\Api;
use stdClass;

class Connection
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var AhamoveHelper
     */
    protected $ahamoveHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        Api                  $api,
        ResourceConnection   $resourceConnection,
        AhamoveHelper        $ahamoveHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->api = $api;
        $this->resourceConnection = $resourceConnection;
        $this->ahamoveHelper = $ahamoveHelper;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array|mixed|stdClass
     */
    public function reloadToken()
    {
        $params = [
            'api_key' => $this->ahamoveHelper->getAPIKey(),
            'mobile' => $this->ahamoveHelper->getMobilePhoneValue()
        ];

        return $this->api->name("Reload Token")
            ->withContentType('application/json')
            ->withData($params)
            ->to(Config::GET_REFRESH_TOKEN)
            ->asJsonResponse(true)
            ->post();
    }

    /**
     * Fetch Data From Api
     *
     * @param $url
     * @param $name
     * @return array|mixed|stdClass
     */
    public function getDataFromApi($url, $name, $data = null)
    {
        return $this->api->name($name)
            ->withContentType('application/json')
            ->withHeader('Authorization: Bearer ' . $this->ahamoveHelper->getToken())
            ->to($url)
            ->asJsonResponse(true)
            ->get();
    }

    /**
     * @param string $tableName
     * @param array $data
     * @param array $pairOfColAndVal
     */
    public function insertData($tableName, $data, $pairOfColAndVal = [])
    {
        if (!$this->checkRecordExist($tableName, $data, $pairOfColAndVal)) {
            $this->resourceConnection->getConnection()->insert(
                $this->resourceConnection->getTableName($tableName),
                $data
            );
        }
    }

    /**
     * @param string $tableName
     * @param $data
     * @param array $pairOfColAndVal
     * @return bool
     */
    private function checkRecordExist($tableName, $data, $pairOfColAndVal = []): bool
    {
        $checkingFlag = false;

        if ($pairOfColAndVal) {
            $connection = $this->resourceConnection->getConnection();
            $sql = $connection->select()->from(
                ['mainTable' => $this->resourceConnection->getTableName($tableName)],
                $pairOfColAndVal['col']
            )->where($pairOfColAndVal['col'] . ' = ?', $pairOfColAndVal['val']);

            $rows = $connection->fetchAll($sql);

            if (count($rows)) {
                $checkingFlag = true;
                $this->updateRecore($tableName, $data, $pairOfColAndVal);
            }
        }

        return $checkingFlag;
    }

    /**
     * @param string $tableName
     * @param $data
     * @param array $pairOfColAndVal
     * @return void
     */
    private function updateRecore($tableName, $data, $pairOfColAndVal = []): void
    {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName($tableName),
            $data,
            [$pairOfColAndVal['col'] . '= ? ' => $pairOfColAndVal['val']]
        );
    }

    public function insertProvinceCity()
    {
        $objectManager = ObjectManager::getInstance();
        $ahamoveCityCollection = $objectManager->create('\Secomm\Ahamove\Model\ResourceModel\AhamoveCity\AhamoveCityCollection');
        $tableNameRegion = $this->resourceConnection->getTableName('directory_country_region');
        $tableNameRegionName = $this->resourceConnection->getTableName('directory_country_region_name');

        foreach ($ahamoveCityCollection->getItems() as $item) {
            $binds = ['country_id' => $item->getCountryId(), 'code' => $item->getCityId(), 'default_name' => $item->getName()];
            if (!$this->checkRecordExist($tableNameRegion, $binds, ['col' => 'code', 'val' => $item->getCityId()])) {
                $this->resourceConnection->getConnection()->insert($tableNameRegion, $binds);
                $regionId = $this->resourceConnection->getConnection()->lastInsertId($tableNameRegion);
                $binds = ['locale' => 'vi_VN', 'region_id' => $regionId, 'name' => $item->getNameViVn()];
                $this->resourceConnection->getConnection()->insert($tableNameRegionName, $binds);
            }

        }
    }
}
