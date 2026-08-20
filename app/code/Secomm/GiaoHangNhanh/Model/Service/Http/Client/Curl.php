<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Http\Client;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Logger\Logger;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ClientException;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ClientInterface;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ConverterException;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ConverterInterface;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\TransferInterface;
use Magento\Framework\HTTP\Client\Curl as CurlClient;
use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * Class Curl
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Http\Client
 */
class Curl implements ClientInterface
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

        /** @var CurlClient $client */
        $client = $this->clientFactory->create();

        $headers = $transferObject->getHeaders();
        if (!empty($headers)) {
            foreach ($headers as $name => $value) {
                if (is_string($name)) {
                    $client->addHeader($name, $value);
                } else {
                    if (strpos($value, ':') !== false) {
                        [$headerName, $headerValue] = explode(':', $value, 2);
                        $client->addHeader(trim($headerName), trim($headerValue));
                    }
                }
            }
        }

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
