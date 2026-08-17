<?php

namespace Secomm\Ahamove\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
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
     * Reload token via Ahamove API
     *
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
     * @param string $url
     * @param string $name
     * @param array|null $data
     * @return array|mixed|stdClass
     */
    public function getDataFromApi(string $url, string $name, ?array $data = null)
    {
        return $this->api->name($name)
            ->withContentType('application/json')
            ->withHeader('Authorization: Bearer ' . $this->ahamoveHelper->getToken())
            ->to($url)
            ->asJsonResponse(true)
            ->get();
    }

    /**
     * Insert or update a record (upsert by column).
     *
     * Used by CLI commands for bulk city data synchronization.
     * Uses ResourceConnection directly for efficiency in bulk operations.
     *
     * @param string $tableName
     * @param array $data Column-value pairs to insert/update
     * @param array $uniqueKey Unique key for conflict detection ['col' => string, 'val' => mixed]
     * @return void
     */
    public function insertData(string $tableName, array $data, array $uniqueKey = []): void
    {
        if (!$this->checkRecordExist($tableName, $data, $uniqueKey)) {
            $this->resourceConnection->getConnection()->insert(
                $this->resourceConnection->getTableName($tableName),
                $data
            );
        }
    }

    /**
     * Check if a record exists by unique key column.
     *
     * @param string $tableName
     * @param array $data
     * @param array $uniqueKey
     * @return bool
     */
    private function checkRecordExist(string $tableName, array $data, array $uniqueKey = []): bool
    {
        if (empty($uniqueKey)) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();
        $sql = $connection->select()->from(
            ['mainTable' => $this->resourceConnection->getTableName($tableName)],
            [$uniqueKey['col']]
        )->where($uniqueKey['col'] . ' = ?', $uniqueKey['val']);

        $rows = $connection->fetchAll($sql);

        if (count($rows)) {
            $this->updateRecord($tableName, $data, $uniqueKey);
            return true;
        }

        return false;
    }

    /**
     * Update an existing record by unique key column.
     *
     * @param string $tableName
     * @param array $data
     * @param array $uniqueKey
     * @return void
     */
    private function updateRecord(string $tableName, array $data, array $uniqueKey = []): void
    {
        $this->resourceConnection->getConnection()->update(
            $this->resourceConnection->getTableName($tableName),
            $data,
            [$uniqueKey['col'] . '= ?' => $uniqueKey['val']]
        );
    }
}
