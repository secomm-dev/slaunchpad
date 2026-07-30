<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */

namespace Secomm\ZaloPay\Gateway\Http\Client;

use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\LaminasClientFactory;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\ConverterInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class Zend implements ClientInterface
{
    /**
     * @var LaminasClientFactory
     */
    private LaminasClientFactory $clientFactory;

    /**
     * @var ConverterInterface | null
     */
    private ?ConverterInterface $converter;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param LaminasClientFactory $clientFactory
     * @param Logger $logger
     * @param ConverterInterface | null $converter
     */
    public function __construct(
        LaminasClientFactory  $clientFactory,
        Logger                $logger,
        ConverterInterface    $converter = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->converter = $converter;
        $this->logger = $logger;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws ClientException
     * @throws ConverterException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $log = [
            'request' => $transferObject->getBody(),
            'request_uri' => $transferObject->getUri()
        ];
        $result = [];
        /** @var LaminasClient $client */
        $client = $this->clientFactory->create();
        $client->setOptions($transferObject->getClientConfig());
        $client->setMethod($transferObject->getMethod());
        $client->setParameterPost($transferObject->getBody());
        $client->setHeaders($transferObject->getHeaders());
        $client->setUrlEncodeBody($transferObject->shouldEncode());
        $client->setUri($transferObject->getUri());

        try {
            $response = $client->send();
            $result = $this->converter ? $this->converter->convert($response->getBody()) : $response->getBody();
            $log['response'] = $result;
        } catch (\Exception $e) {
            throw new ClientException(
                __($e->getMessage())
            );
        } finally {
            $this->logger->debug($log);
        }

        return $result;
    }
}
