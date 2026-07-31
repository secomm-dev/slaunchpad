<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration\Http\Client;

use Secomm\GiaoHangNhanh\Model\Integration\Http\ClientException;
use Secomm\GiaoHangNhanh\Model\Integration\Http\ClientInterface;
use Secomm\GiaoHangNhanh\Model\Integration\Http\ConverterException;
use Secomm\GiaoHangNhanh\Model\Integration\Http\TransferInterface;
use Secomm\GiaoHangNhanh\Model\Integration\Http\ConverterInterface;
use Secomm\GiaoHangNhanh\Model\Integration\Logger\Logger;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\LaminasClientFactory;

class Zend implements ClientInterface
{
    /**
     * @var LaminasClientFactory
     */
    private $clientFactory;

    /**
     * @var ConverterInterface | null
     */
    private $converter;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param LaminasClientFactory $clientFactory
     * @param Logger $logger
     * @param ConverterInterface | null $converter
     */
    public function __construct(
        LaminasClientFactory $clientFactory,
        Logger $logger,
        ?ConverterInterface $converter = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->converter = $converter;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function request(TransferInterface $transferObject)
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

        $method = strtoupper((string)$transferObject->getMethod());
        switch ($method) {
            case 'GET':
                if (is_array($transferObject->getBody())) {
                    $client->setParameterGet($transferObject->getBody());
                }
                break;
            case 'POST':
                if (is_array($transferObject->getBody())) {
                    $client->setParameterPost($transferObject->getBody());
                } else {
                    $client->setRawBody($transferObject->getBody());
                }
                break;
            default:
                throw new \LogicException(
                    sprintf(
                        'Unsupported HTTP method %s',
                        $transferObject->getMethod()
                    )
                );
        }

        $client->setHeaders($transferObject->getHeaders());
        $client->setUrlEncodeBody($transferObject->shouldEncode());
        $client->setUri($transferObject->getUri());

        try {
            $response = $client->send();

            $result = $this->converter
                ? $this->converter->convert($response->getBody())
                : [$response->getBody()];
            $log['response'] = $result;
        } catch (\Exception $e) {
            if ($e instanceof ConverterException) {
                throw $e;
            }
            throw new ClientException(
                __($e->getMessage())
            );
        } finally {
            $this->logger->debug($log);
        }

        return $result;
    }
}
