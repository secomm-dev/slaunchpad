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
        try {
            $response = $this->reloadToken();
            if ($response['status'] == Status::STATUS_CODE_SUCCESS) {
                $dataContent = $response['content'];
                $this->configWriter->save(
                    Config::STAGING_TOKEN,
                    $dataContent['token']
                );
                $this->configWriter->save(
                    Config::STAGING_TOKEN_REFRESH,
                    $dataContent['refresh_token']
                );
                $this->helperDataAhamove->flushCache();
                $this->logger->info('Reload token success', [], __METHOD__);
            } else {
                throw new \Exception(json_encode($response['content']));
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        } finally {
            $data->setResponse($response);
        }
    }

    /**
     * Call API to get Ahamove token
     *
     * @return array
     * @throws \Exception
     */
    public function reloadToken(): array
    {
        $params = [
            'api_key' => $this->helperDataAhamove->getAPIKey(),
            'mobile' => $this->helperDataAhamove->getMobilePhoneValue()
        ];
        $url = $this->helperDataAhamove->getUrlAhamove() . Config::GET_REFRESH_TOKEN;
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
