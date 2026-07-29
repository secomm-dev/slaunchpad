<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Boolfly. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    info@boolfly.com
 * *  @project   Giao hang nhanh
 */
namespace Boolfly\GiaoHangNhanh\Model\Service\Http\Client;

use Boolfly\IntegrationBase\Model\Logger\Logger;
use Boolfly\IntegrationBase\Model\Service\Http\ClientException;
use Boolfly\IntegrationBase\Model\Service\Http\ClientInterface;
use Boolfly\IntegrationBase\Model\Service\Http\ConverterException;
use Boolfly\IntegrationBase\Model\Service\Http\ConverterInterface;
use Boolfly\IntegrationBase\Model\Service\Http\TransferInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * Class Zend
 *
 * @package Boolfly\GiaoHangNhanh\Model\Service\Http\Client
 */
class Zend implements ClientInterface
{
    /**
     * @var CurlFactory
     */
    private $clientFactory;

    /**
     * @var ConverterInterface|null
     */
    private $converter;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param CurlFactory            $clientFactory
     * @param Logger                 $logger
     * @param ConverterInterface|null $converter
     */
    public function __construct(
        CurlFactory $clientFactory,
        Logger $logger,
        ConverterInterface $converter = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->converter     = $converter;
        $this->logger        = $logger;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws ClientException
     * @throws ConverterException
     */
    public function request(TransferInterface $transferObject)
    {
        $log = [
            'request'     => $this->converter
                ? $this->converter->convert($transferObject->getBody())
                : $transferObject->getBody(),
            'request_uri' => $transferObject->getUri(),
        ];

        $result = [];

        /** @var Curl $client */
        $client = $this->clientFactory->create();

        // Set headers individually to ensure keys like Authorization are preserved
        $headers = $transferObject->getHeaders();
        if (!empty($headers)) {
            foreach ($headers as $name => $value) {
                if (is_string($name)) {
                    // Associative: ['Authorization' => 'Bearer xxx', 'Content-Type' => 'application/json']
                    $client->addHeader($name, $value);
                } else {
                    // Indexed: ['Authorization: Bearer xxx']
                    if (strpos($value, ':') !== false) {
                        [$headerName, $headerValue] = explode(':', $value, 2);
                        $client->addHeader(trim($headerName), trim($headerValue));
                    }
                }
            }
        }

        // Set connection/request timeout from client config if provided
        $clientConfig = $transferObject->getClientConfig();
        if (!empty($clientConfig['timeout'])) {
            $client->setTimeout((int)$clientConfig['timeout']);
        }

        try {
            $method = strtoupper($transferObject->getMethod());
            $uri    = $transferObject->getUri();
            $body   = $transferObject->getBody();

            switch ($method) {
                case 'POST':
                    $client->post($uri, $body);
                    break;
                case 'PUT':
                    $client->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
                    $client->setOption(CURLOPT_POSTFIELDS, $body);
                    $client->post($uri, $body);
                    break;
                case 'DELETE':
                    $client->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');
                    $client->get($uri);
                    break;
                case 'GET':
                default:
                    $client->get($uri);
                    break;
            }

            $responseBody    = $client->getBody();
            $result          = $this->converter
                ? $this->converter->convert($responseBody)
                : [$responseBody];
            $log['response'] = $result;

        } catch (ConverterException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ClientException(__($e->getMessage()));
        } finally {
            $this->logger->debug($log);
        }

        return $result;
    }
}
