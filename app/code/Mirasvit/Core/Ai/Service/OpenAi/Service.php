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

namespace Mirasvit\Core\Ai\Service\OpenAi;

use Mirasvit\Core\Ai\Service\AbstractAiService;
use Mirasvit\Core\Ai\Api\Data\AiRequestInterface;
use Mirasvit\Core\Ai\Api\Data\AiResponseInterface;

class Service extends AbstractAiService
{
    public function getProvider(): string
    {
        return ConfigProvider::PROVIDER_NAME;
    }

    public function getSupportedModels(): array
    {
        $providerConfig = $this->configProvider->getProviderConfig($this->getProvider());

        return $providerConfig->getAllModels();
    }

    public function validateRequest(AiRequestInterface $request): bool
    {
        if (empty($request->getPrompt())) {
            throw new \InvalidArgumentException((string)__('Request prompt cannot be empty'));
        }

        $model           = $this->resolveModel($request->getModel());
        $providerConfig  = $this->configProvider->getProviderConfig($this->getProvider());
        $supportedModels = $providerConfig->getAllModels();

        if (!in_array($model, $supportedModels)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported OpenAI model: %s. Supported models: %s',
                $model,
                implode(', ', $supportedModels)
            ));
        }

        $temperature = $request->getTemperature();
        if ($temperature !== null && ($temperature < 0 || $temperature > 2)) {
            throw new \InvalidArgumentException((string)__('Temperature must be between 0 and 2'));
        }

        $maxTokens = $request->getMaxTokens();
        if ($maxTokens !== null && $maxTokens <= 0) {
            throw new \InvalidArgumentException((string)__('Max tokens must be greater than 0'));
        }

        $frequencyPenalty = $request->getFrequencyPenalty();
        if ($frequencyPenalty !== null && ($frequencyPenalty < -2 || $frequencyPenalty > 2)) {
            throw new \InvalidArgumentException((string)__('Frequency penalty must be between -2 and 2'));
        }

        return parent::validateRequest($request);
    }

    protected function getEndpoint(AiRequestInterface $request): string
    {
        $providerConfig = $this->configProvider->getProviderConfig($this->getProvider());
        $model          = $request->getModel() ? : $this->configProvider->getDefaultModel($this->getProvider());

        return $providerConfig->getEndpointForModel($model);
    }

    protected function buildRequestData(AiRequestInterface $request): array
    {
        $model          = $this->resolveModel($request->getModel());
        $providerConfig = $this->configProvider->getProviderConfig($this->getProvider());

        return $this->buildResponsesRequest($request, $model, $providerConfig);
    }


    private function buildResponsesRequest(AiRequestInterface $request, string $model, $providerConfig): array
    {
        $data = [
            'model' => $model,
            'input' => $this->buildInput($request),
        ];

        if (!$this->isGpt5Model($model)) {
            $temperature = $this->getTemperature($request);
            if ($temperature !== $providerConfig->getTemperature()) {
                $data['temperature'] = $temperature;
            }
        }

        $maxTokens = $this->getMaxTokens($request, $model);
        if ($maxTokens > 0) {
            $data['max_output_tokens'] = $maxTokens;
        }

        $textConfig = $this->buildTextOutputConfig($request);
        if (!empty($textConfig)) {
            $data['text'] = $textConfig;
        }

        $systemPrompt = $request->getSystemPrompt();
        if (!empty($systemPrompt)) {
            $data['instructions'] = $systemPrompt;
        }

        $tools = $this->buildTools($request->getTools(), $model);
        if (!empty($tools)) {
            $data['tools'] = $tools;
        }

        // Reasoning models (gpt-5 family) accept a reasoning.effort parameter
        // to bound how many internal reasoning tokens are spent. For tasks
        // like "describe this image in 60 words" we don't need extended
        // reasoning, and on small reasoning models (gpt-5-nano) the default
        // medium effort can overflow the context window.
        // Consumers pass via $request->addParameter('reasoning_effort', 'minimal'|'low'|'medium'|'high').
        $params = $request->getParameters();
        if (!empty($params['reasoning_effort']) && $this->isGpt5Model($model)) {
            $effort = (string)$params['reasoning_effort'];
            if (in_array($effort, ['minimal', 'low', 'medium', 'high'], true)) {
                $data['reasoning'] = ['effort' => $effort];
            }
        }

        return $data;
    }

    private function buildTools(array $tools, string $model): array
    {
        $result = [];

        if (in_array('web_search', $tools) && $this->supportsWebSearch($model)) {
            $result[] = [
                'type'                => 'web_search',
                'search_context_size' => 'medium',
            ];
        }

        return $result;
    }

    private function supportsWebSearch(string $model): bool
    {
        $unsupported = ['gpt-4.1-nano', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'];

        return !in_array($model, $unsupported);
    }

    protected function processResponse(array $rawResponse, AiRequestInterface $originalRequest): AiResponseInterface
    {
        $model    = $originalRequest->getModel();
        $response = $this->responseFactory->create();

        $response->setProvider($this->getProvider());
        $response->setModel($model);
        $response->setCreatedAt(date('Y-m-d H:i:s'));

        try {
            return $this->processResponsesResponse($rawResponse, $response);
        } catch (\Exception $e) {
            return $this->createErrorResponse($e->getMessage(), 0);
        }
    }


    private function resolveModel(?string $requestedModel): string
    {
        if (empty($requestedModel) || $requestedModel === 'default') {
            return $this->configProvider->getDefaultModel($this->getProvider());
        }

        return $requestedModel;
    }

    private function buildInput(AiRequestInterface $request)
    {
        $prompt      = $request->getPrompt();
        $attachments = $request->getAttachments();

        if (empty($attachments)) {
            return is_array($prompt) ? $prompt : $prompt;
        }

        // Responses API requires content blocks wrapped in a message object
        $content = [];

        foreach ($attachments as $att) {
            if ($att['type'] === 'image') {
                $content[] = [
                    'type'      => 'input_image',
                    'image_url' => $att['source'] === 'url'
                        ? $att['url']
                        : 'data:' . $att['media_type'] . ';base64,' . $att['data'],
                ];
            } elseif ($att['type'] === 'document' || $att['type'] === 'text_file') {
                $content[] = [
                    'type'      => 'input_file',
                    'filename'  => $att['filename'] ?? 'file',
                    'file_data' => 'data:' . $att['media_type'] . ';base64,' . $att['data'],
                ];
            }
        }

        $content[] = [
            'type' => 'input_text',
            'text' => is_array($prompt) ? implode("\n", $prompt) : $prompt,
        ];

        return [
            [
                'type'    => 'message',
                'role'    => 'user',
                'content' => $content,
            ],
        ];
    }


    private function buildTextOutputConfig(AiRequestInterface $request): array
    {
        $textConfig = [];
        $parameters = $request->getParameters();

        if (isset($parameters['response_format'])) {
            if (in_array(strtolower($parameters['response_format']), ['json_schema', 'json_object', 'json'])) {
                $textConfig['format'] = [
                    'type' => 'json_object',
                ];

                $systemPrompt     = $request->getSystemPrompt();
                $jsonInstructions = (string)__('Format output as JSON.');

                if (!empty($systemPrompt)) {
                    $systemPrompt = strpos(strtolower($systemPrompt), 'json') !== false
                        ? $systemPrompt
                        : $systemPrompt . ' ' . $jsonInstructions;
                } else {
                    $systemPrompt = $jsonInstructions;
                }

                $request->setSystemPrompt($systemPrompt);
            } else {
                $textConfig['format'] = ['type' => 'text'];
            }
        }

        return $textConfig;
    }

    private function getTemperature(AiRequestInterface $request): float
    {
        $requestTemperature = $request->getTemperature();

        if ($requestTemperature !== null && $requestTemperature >= 0 && $requestTemperature <= 2) {
            return $requestTemperature;
        }

        return $this->configProvider->getTemperature();
    }

    private function getMaxTokens(AiRequestInterface $request, string $model): int
    {
        $requestMaxTokens = $request->getMaxTokens();
        $providerConfig   = $this->configProvider->getProviderConfig($this->getProvider());

        if ($requestMaxTokens !== null && $requestMaxTokens > 0) {
            $modelLimit = $providerConfig->getTokenLimitForModel($model);

            return min($requestMaxTokens, $modelLimit);
        }

        $configMaxTokens = $this->configProvider->getMaxTokens();
        if ($configMaxTokens > 0) {
            return min($configMaxTokens, $providerConfig->getTokenLimitForModel($model));
        }

        return 0;
    }

    private function isGpt5Model(string $model): bool
    {
        return strpos($model, 'gpt-5') === 0;
    }


    private function processResponsesResponse(array $rawResponse, AiResponseInterface $response): AiResponseInterface
    {
        if (isset($rawResponse['error']) && is_array($rawResponse['error'])) {
            $error        = $rawResponse['error'];
            $errorMessage = sprintf(
                'OpenAI API Error: %s (Code: %s)',
                $error['message'] ?? 'Unknown error',
                $error['code'] ?? 'unknown'
            );

            return $this->createErrorResponse($errorMessage, 0);
        }

        if (isset($rawResponse['status']) && $rawResponse['status'] === 'incomplete') {
            return $this->createErrorResponse((string)__('OpenAI API returned an incomplete response because the model\'s context window tokens number was exceeded.'), 0);
        }

        if (!isset($rawResponse['output']) || !is_array($rawResponse['output'])) {
            return $this->createErrorResponse((string)__('OpenAI API returned no content in the response.'), 0);
        }

        foreach ($rawResponse['output'] as $output) {
            if ($output['type'] !== 'message') {
                continue;
            }

            if (isset($output['status']) && $output['status'] === 'incomplete') {
                return $this->createErrorResponse((string)__('OpenAI API returned an incomplete response because the model\'s context window tokens number was exceeded.'), 0);
            }

            if (!isset($output['content']) || !is_array($output['content'])) {
                continue;
            }

            foreach ($output['content'] as $content) {
                if ($content['type'] === 'refusal' && isset($content['refusal'])) {
                    return $this->createErrorResponse($content['refusal'], 0);
                }

                if ($content['type'] === 'output_text' && isset($content['text'])) {
                    $response->setStatus(AiResponseInterface::STATUS_SUCCESS);
                    $response->setContent(trim($content['text']));

                    if (isset($rawResponse['usage'])) {
                        $usage        = $rawResponse['usage'];
                        $totalTokens  = (int)($usage['total_tokens'] ?? 0);
                        $inputTokens  = (int)($usage['input_tokens'] ?? 0);
                        $outputTokens = (int)($usage['output_tokens'] ?? 0);

                        $response->setPromptTokens($inputTokens);
                        $response->setCompletionTokens($outputTokens);
                        $response->setUsageData([
                            'total_tokens'  => $totalTokens,
                            'input_tokens'  => $inputTokens,
                            'output_tokens' => $outputTokens,
                        ]);
                    }

                    return $response;
                }
            }
        }

        return $this->createErrorResponse((string)__('OpenAI API returned no content in the response.'), 0);
    }

}
