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
            // TASK-MCHN2T: the core payment method logger gates on the Admin
            // Debug Mode flag (`payment/zalopay/debug`, default OFF) before it
            // reaches Monolog; the maskKeys argument makes its recursive filter
            // scrub sensitive keys from the nested response payload too.
            $this->logger->debug($log, $this->sensitiveKeys());
        }

        return $result;
    }

    /**
     * Mask signature/secret fields in the request payload before it is written to the debug log.
     *
     * TASK-MCHN2T: the request body carries key1 (builder output consumed by the MAC
     * plugins), so it must be masked alongside key2; the same list is handed to the
     * core payment method logger as maskKeys so its recursive filter also scrubs the
     * nested response payload (previously only the flat request body was pre-masked).
     *
     * @param mixed $body
     * @return mixed
     */
    private function maskSensitiveData($body)
    {
        if (!is_array($body)) {
            return $body;
        }

        foreach ($body as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, $this->sensitiveKeys(), true)) {
                $body[$key] = '****';
            }
        }

        return $body;
    }

    /**
     * Sensitive key names that must never appear unmasked in debug output.
     *
     * TASK-MCHN2T correction round: `app_user` is included - ZaloPay defines
     * it as the merchant-side user identifier (id/username/name/phone/email),
     * so the no-unnecessary-PII logging contract requires it masked in the
     * provider debug output (flat request pre-mask AND the recursive
     * response mask handed to the core payment method logger).
     *
     * @return array
     */
    private function sensitiveKeys(): array
    {
        return [
            'mac', 'signature', 'hmac', 'secret', 'secretkey', 'key1', 'key2',
            'access_key', 'secret_key', 'app_user',
        ];
    }
}
