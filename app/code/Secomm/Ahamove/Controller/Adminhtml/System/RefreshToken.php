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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Secomm\Ahamove\Helper\Data as HelperDataAhamove;

class RefreshToken extends Action implements HttpPostActionInterface
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
            $storeId = $this->getRequest()->getParam('store');
            $websiteId = $this->getRequest()->getParam('website');

            if ($storeId) {
                $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
                $scopeId = $storeId;
            } elseif ($websiteId) {
                $scope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
                $scopeId = $websiteId;
            } else {
                $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
                $scopeId = 0;
            }

            $response = $this->connection->reloadToken();

            if ($response && isset($response->status) && $response->status == Status::STATUS_CODE_SUCCESS) {
                $dataContent = $response->content;
                if ($this->helperDataAhamove->isStagingMode($scopeId)){
                    $this->configWriter->save(
                        Config::STAGING_TOKEN,
                        $dataContent['token'],
                        $scope,
                        $scopeId);
                    $this->configWriter->save(
                        Config::STAGING_TOKEN_REFRESH,
                        $dataContent['refresh_token'],
                        $scope,
                        $scopeId);
                }else{
                    $this->configWriter->save(
                        Config::PRODUCTION_TOKEN,
                        $dataContent['token'],
                        $scope,
                        $scopeId);
                    $this->configWriter->save(
                        Config::PRODUCTION_TOKEN_REFRESH,
                        $dataContent['refresh_token'],
                        $scope,
                        $scopeId);
                }

                $this->helperDataAhamove->flushCache();
                $resultJson->setData([
                    'message' => __('Update Success.')
                ]);
            } else {
                $resultJson->setData([
                    'message' => __('Update Fails')
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $resultJson;
    }
}
