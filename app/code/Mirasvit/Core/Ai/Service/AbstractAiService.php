<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\Core\Ai\Service;

use Mirasvit\Core\Ai\Logger\AiLogger;
use Mirasvit\Core\Ai\Model\ConfigProvider;
use Mirasvit\Core\Ai\Model\ResponseFactory;
use Mirasvit\Core\Ai\Service\AiClientInterface;
use Mirasvit\Core\Ai\Api\Data\AiRequestInterface;
use Mirasvit\Core\Ai\Api\Data\AiResponseInterface;
use Mirasvit\Core\Service\SerializeService;

abstract class AbstractAiService
{

    protected $client;

    protected $configProvider;

    protected $responseFactory;

    protected $logger;

    public function __construct(
        AiClientInterface $client,
        ConfigProvider    $configProvider,
        ResponseFactory   $responseFactory,
        AiLogger          $logger
    ) {
        $this->client          = $client;
        $this->configProvider  = $configProvider;
        $this->responseFactory = $responseFactory;
        $this->logger          = $logger;
    }

    public function sendRequest(AiRequestInterface $request): AiResponseInterface
    {
        try {
            $this->validateRequest($request);

            if (!$this->isAvailable($request)) {
                throw new \Exception((string)__('%1 service is not properly configured or available', ucfirst($this->getProvider())));
            }

            $this->prepareClient($request);

            $endpoint = $this->getEndpoint($request);
            $data     = $this->buildRequestData($request);
            $headers  = $this->buildRequestHeaders();

            $this->logRequest($request, $endpoint, $data);

            $rawResponse = $this->client->sendRequest($endpoint, AiClientInterface::HTTP_METHOD_POST, $data, $headers);

            $response = $this->processResponse($rawResponse, $request);

            $this->logResponse($response, $rawResponse);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'AI service request failed: %s',
                $e->getMessage()
            ));

            return $this->createErrorResponse($e->getMessage(), $e->getCode());
        }
    }

    public function isAvailable(?AiRequestInterface $request = null): bool
    {
        if (!$this->configProvider->isEnabled()) {
            return false;
        }

        if (!$this->configProvider->isProviderEnabled($this->getProvider())) {
            return false;
        }

        $this->prepareClient($request);

        return $this->client->isConfigured();
    }

    public function validateRequest(AiRequestInterface $request): bool
    {
        if (!$request->isValid()) {
            throw new \InvalidArgumentException((string)__('Invalid AI request data'));
        }

        if (empty($request->getPrompt())) {
            throw new \InvalidArgumentException((string)__('Request prompt cannot be empty'));
        }

        $supportedModels = $this->getSupportedModels();
        if (!empty($supportedModels) && !in_array($request->getModel(), $supportedModels)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported model: %s. Supported models: %s',
                $request->getModel(),
                implode(', ', $supportedModels)
            ));
        }

        return true;
    }

    abstract protected function getEndpoint(AiRequestInterface $request): string;

    abstract protected function buildRequestData(AiRequestInterface $request): array;

    abstract protected function processResponse(array $rawResponse, AiRequestInterface $originalRequest): AiResponseInterface;

    abstract public function getSupportedModels(): array;

    abstract public function getProvider(): string;

    protected function prepareClient(?AiRequestInterface $request = null): void
    {
        $providerConfig = $this->configProvider->getProviderConfig($this->getProvider());

        $apiKey = null;
        if ($request && $request->getApiKey()) {
            $apiKey = $request->getApiKey();
        } else {
            $apiKey = $this->configProvider->getApiKey($this->getProvider());
        }

        $baseUrl = $providerConfig->getBaseUrl();
        $timeout = $this->configProvider->getTimeout();

        $this->client->setApiKey($apiKey);
        $this->client->setBaseUrl($baseUrl);

        if ($timeout > 0) {
            $this->client->setTimeout($timeout);
        }
    }

    protected function buildRequestHeaders(): array
    {
        return [
            AiClientInterface::HEADER_CONTENT_TYPE => AiClientInterface::CONTENT_TYPE_JSON,
            AiClientInterface::HEADER_USER_AGENT   => $this->getUserAgent(),
        ];
    }

    protected function createErrorResponse(string $errorMessage, int $errorCode = 0): AiResponseInterface
    {
        $response = $this->responseFactory->create();

        $response->setStatus(AiResponseInterface::STATUS_ERROR);
        $response->setErrorMessage($errorMessage);
        $response->setErrorCode((string)$errorCode);
        $response->setProvider($this->getProvider());
        $response->setCreatedAt(date('Y-m-d H:i:s'));

        return $response;
    }

    protected function getUserAgent(): string
    {
        return 'Mirasvit-Core/1.0';
    }

    public function getDefaultModel(): string
    {
        return $this->configProvider->getDefaultModel($this->getProvider());
    }

    public function isModelAvailable(string $model): bool
    {
        return in_array($model, $this->getSupportedModels());
    }

    protected function logRequest(AiRequestInterface $request, string $endpoint, array $data): void
    {
        $tools      = $request->getTools();
        $attachments = $request->getAttachments();

        $this->logger->info(sprintf(
            'AI request sent to %s: Model=%s, Provider=%s, Prompt length=%d%s%s',
            $endpoint,
            $request->getModel() ? : 'default',
            $request->getProvider() ? : $this->getProvider(),
            strlen($request->getPrompt()),
            !empty($tools) ? ', Tools=' . implode(',', $tools) : '',
            !empty($attachments) ? ', Attachments=' . count($attachments) : ''
        ));

        // Log the raw request body (debug level only)
        $requestBody = SerializeService::encodeReadable($data);
        $this->logger->debug(sprintf(
            '%s Request Body: %s',
            ucfirst($this->getProvider()),
            $requestBody
        ));

        $this->logger->debug(sprintf(
            "\n\n==== %s REQUEST ===\nEndpoint: %s\nBody:\n%s\n==== END %s REQUEST ===\n\n",
            strtoupper($this->getProvider()),
            $endpoint,
            $requestBody,
            strtoupper($this->getProvider())
        ));
    }

    protected function logResponse(AiResponseInterface $response, array $rawResponse = []): void
    {
        if ($response->isSuccess()) {
            $this->logger->info(sprintf(
                'AI response received: Status=%s, Provider=%s, Content length=%d, Tokens=%d',
                $response->getStatus(),
                $response->getProvider() ? : $this->getProvider(),
                strlen($response->getContent()),
                $response->getTokensUsed()
            ));

            // Log the raw response body (debug level only)
            $responseBody = SerializeService::encodeReadable($rawResponse);
            $this->logger->debug(sprintf(
                '%s Response Body: %s',
                ucfirst($this->getProvider()),
                $responseBody
            ));

            $this->logger->debug(sprintf(
                "\n\n==== %s RESPONSE ===\nBody:\n%s\n==== END %s RESPONSE ===\n\n",
                strtoupper($this->getProvider()),
                $responseBody,
                strtoupper($this->getProvider())
            ));
        } else {
            $this->logger->warning(sprintf(
                'AI response error: Status=%s, Provider=%s, Error=%s',
                $response->getStatus(),
                $response->getProvider() ? : $this->getProvider(),
                $response->getErrorMessage()
            ));

            // Log raw error response if available
            if (!empty($rawResponse)) {
                $this->logger->debug(sprintf(
                    '%s Error Response Body: %s',
                    ucfirst($this->getProvider()),
                    SerializeService::encodeReadable($rawResponse)
                ));
            }
        }
    }

    public function getModelInfo(string $model): array
    {
        $providerConfig = $this->configProvider->getProviderConfig($this->getProvider());

        return [
            'name'        => $model,
            'provider'    => $this->getProvider(),
            'endpoint'    => $this->getModelEndpoint($model, $providerConfig),
            'token_limit' => $providerConfig->getTokenLimitForModel($model),
        ];
    }

    protected function getModelEndpoint(string $model, $providerConfig): string
    {
        if (method_exists($providerConfig, 'getEndpointForModel')) {
            return $providerConfig->getEndpointForModel($model);
        }

        $endpoint = $providerConfig->getEndpoint();
        if (strpos($endpoint, '{model}') !== false) {
            return str_replace('{model}', $model, $endpoint);
        }

        return $endpoint;
    }
}
