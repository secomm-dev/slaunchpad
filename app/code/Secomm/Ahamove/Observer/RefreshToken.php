<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Observer;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\Ahamove\Helper\Data as HelperDataAhamove;
use Secomm\Ahamove\Logger\Logger;
use Secomm\Ahamove\Model\Config;
use Secomm\Ahamove\Model\Config\Source\ApiRequest\Status;

class RefreshToken implements ObserverInterface
{
    public function __construct(
        protected WriterInterface   $configWriter,
        protected HelperDataAhamove $helperDataAhamove,
        protected Logger            $logger,
    ) {
    }

    /**
     * This function will be executed when the token has expired or is changed in another environment
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $response = null;
        $data = $observer->getData('data');
        $storeId = $observer->getData('store_id');
        try {
            $response = $this->reloadToken($storeId);
            if (isset($response['status']) && $response['status'] == Status::STATUS_CODE_SUCCESS) {
                $dataContent = $response['content'];
                $isStaging = $this->helperDataAhamove->isStagingMode($storeId);
                $tokenPath = $isStaging ? Config::STAGING_TOKEN : Config::PRODUCTION_TOKEN;
                $refreshTokenPath = $isStaging ? Config::STAGING_TOKEN_REFRESH : Config::PRODUCTION_TOKEN_REFRESH;

                $scope = $storeId ? \Magento\Store\Model\ScopeInterface::SCOPE_STORES : \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
                $scopeId = $storeId ?: 0;

                $this->configWriter->save($tokenPath, $dataContent['token'], $scope, $scopeId);
                $this->configWriter->save($refreshTokenPath, $dataContent['refresh_token'], $scope, $scopeId);
                $this->helperDataAhamove->flushCache();
                $this->logger->info('Reload token success for store: ' . $scopeId, [], __METHOD__);
            } else {
                throw new \Exception(json_encode($response['content'] ?? []));
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        } finally {
            if ($data) {
                $data->setResponse($response);
            }
        }
    }

    /**
     * Call API to get Ahamove token
     *
     * @param mixed $storeId
     * @return array
     * @throws \Exception
     */
    public function reloadToken($storeId = null): array
    {
        $params = [
            'api_key' => $this->helperDataAhamove->getAPIKey($storeId),
            'mobile' => $this->helperDataAhamove->getMobilePhoneValue($storeId)
        ];
        $url = $this->helperDataAhamove->getUrlAhamove($storeId) . Config::GET_REFRESH_TOKEN;
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            throw new \Exception('Curl error: ' . curl_error($curl));
        }

        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return ['status' => $statusCode, 'content' => json_decode($response, true)];
    }
}
