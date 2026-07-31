<?php
/**
 * MoMo HTTP gateway client (Laminas).
 *
 * Sends the JSON request to MoMo and converts the JSON body to an array.
 * Replaces the legacy raw-curl client (adds timeout + standard error handling).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Http\Client;

use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\LaminasClientFactory;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\ConverterInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class Laminas implements ClientInterface
{
    /**
     * @var LaminasClientFactory
     */
    private LaminasClientFactory $clientFactory;

    /**
     * @var ConverterInterface|null
     */
    private ?ConverterInterface $converter;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor
     *
     * @param LaminasClientFactory $clientFactory
     * @param Logger $logger
     * @param ConverterInterface|null $converter
     */
    public function __construct(
        LaminasClientFactory $clientFactory,
        Logger $logger,
        ConverterInterface $converter = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->converter = $converter;
        $this->logger = $logger;
    }

    /**
     * Place the HTTP request to MoMo and return the decoded response array.
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws ClientException
     * @throws ConverterException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $log = [
            'request' => $transferObject->getBody(),
            'request_uri' => $transferObject->getUri(),
        ];
        $result = [];

        /** @var LaminasClient $client */
        $client = $this->clientFactory->create();
        $client->setOptions($transferObject->getClientConfig());
        $client->setMethod($transferObject->getMethod());
        $client->setHeaders($transferObject->getHeaders());
        $client->setRawBody($transferObject->getBody());
        $client->setUri($transferObject->getUri());

        try {
            $response = $client->send();
            $result = $this->converter ? $this->converter->convert($response->getBody()) : $response->getBody();
            $log['response'] = $result;
        } catch (\Exception $e) {
            throw new ClientException(__($e->getMessage()));
        } finally {
            $this->logger->debug($log);
        }

        return $result;
    }
}
