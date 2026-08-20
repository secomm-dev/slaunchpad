<?php

namespace Secomm\Ahamove\Controller\Adminhtml\System;

use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Config\Source\ApiRequest\Status;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Backend\App\Action\Context;
use Secomm\Ahamove\Helper\Connection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Secomm\Ahamove\Helper\Data as HelperDataAhamove;

class RefreshCityToken extends Action implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var HelperDataAhamove
     */
    protected $helperDataAhamove;

    public function __construct(
        JsonFactory           $resultJsonFactory,
        Connection            $connection,
        LoggerInterface       $logger,
        WriterInterface       $configWriter,
        StoreManagerInterface $storeManager,
        HelperDataAhamove     $helperDataAhamove,
        Context               $context
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->helperDataAhamove = $helperDataAhamove;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $response = $this->connection->getDataFromApi(
                Config::URL_AHAMOVE_CITY, "Sync data City from Ahamove");

            if ($response->status == Status::STATUS_CODE_SUCCESS) {
                $data = $response->content;
                if ($data) {
                    foreach ($data as $item) {
                        $this->connection->insertData(
                            'ahamove_city',
                            [
                                'city_id' => $item['_id'],
                                'country_id' => $item['country_id'],
                                'name' => $item['name'],
                                'name_vi_vn' => $item['name'],
//                                'level' => $item['level']
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
                                        'name_vi_vn' => $dataDetails['name'],
                                        'country_id' => $dataDetails['country_id'],
                                        'location' => $dataDetails['location'],
                                        'contract_number' => $dataDetails['contract_number'] ?? null,
                                        'merchant_contract_number' => $dataDetails['merchant_contract_number'] ?? null,
                                        'animated_url' => $dataDetails['animated_url'] ?? null,
                                        'same_district_delivery' => $dataDetails['same_district_delivery'] ?? null,
//                                        'level' => $dataDetails['level'],
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

                $this->helperDataAhamove->flushCache();
                $resultJson->setData([
                    'message' => __('Refresh Success.')
                ]);
            } else {
                $resultJson->setData([
                    'message' => __('Refresh Fails')
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $resultJson;
    }
}
