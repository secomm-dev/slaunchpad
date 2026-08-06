<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

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
     * @param LaminasClientFactory $clientFactory
     * @param Logger $logger
     * @param ConverterInterface | null $converter
     */
    public function __construct(
        private readonly LaminasClientFactory  $clientFactory,
        private readonly Logger                $logger,
        private ?ConverterInterface            $converter = null
    ) {
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
            'request' => $this->maskSensitiveData($transferObject->getBody()),
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

    /**
     * Mask signature/secret fields in the request payload before it is written to the debug log.
     *
     * @param mixed $body
     * @return mixed
     */
    private function maskSensitiveData($body)
    {
        if (!is_array($body)) {
            return $body;
        }

        $sensitiveKeys = ['mac', 'signature', 'hmac', 'secret', 'secretkey', 'key2', 'access_key', 'secret_key'];
        foreach ($body as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, $sensitiveKeys, true)) {
                $body[$key] = '****';
            }
        }

        return $body;
    }
}
