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

namespace Mirasvit\Core\Ai\Service\Gemini;

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

        return array_merge(
            $providerConfig->getAllModels(),
            array_keys(ConfigProvider::DEPRECATED_MODEL_MAP)
        );
    }

    protected function getEndpoint(AiRequestInterface $request): string
    {
        $providerConfig = $this->configProvider->getProviderConfig($this->getProvider());
        $model          = $request->getModel() ? : $this->configProvider->getDefaultModel($this->getProvider());
        $model          = $providerConfig->resolveModel($model);

        return str_replace('{model}', $model, $providerConfig->getEndpoint());
    }

    protected function buildRequestData(AiRequestInterface $request): array
    {
        $providerConfig = $this->configProvider->getProviderConfig($this->getProvider());

        $parts = $this->buildParts($request);

        $data = [
            'contents' => [
                [
                    'parts' => $parts,
                    'role'  => 'user',
                ],
            ],
        ];

        $systemPrompt = $request->getSystemPrompt();
        if (!empty($systemPrompt)) {
            $data['systemInstruction'] = [
                'parts' => [
                    [
                        'text' => $systemPrompt,
                    ],
                ],
            ];
        }

        $generationConfig = [];

        if ($request->getTemperature() !== null) {
            $generationConfig['temperature'] = max(0.0, min(1.0, $request->getTemperature()));
        } else {
            $generationConfig['temperature'] = $providerConfig->getTemperature();
        }

        $maxTokens = $this->getMaxTokens($request);
        if ($maxTokens > 0) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }

        $generationConfig['topP'] = $providerConfig->getGeminiTopP();
        $generationConfig['topK'] = $providerConfig->getGeminiTopK();

        if ($request->getStopSequences()) {
            $stopSequences = is_string($request->getStopSequences())
                ? explode(',', $request->getStopSequences())
                : $request->getStopSequences();

            $generationConfig['stopSequences'] = array_map('trim', $stopSequences);
        }

        if (!empty($generationConfig)) {
            $data['generationConfig'] = $generationConfig;
        }

        if (in_array('web_search', $request->getTools())) {
            $data['tools'][] = [
                'google_search' => new \stdClass(),
            ];
        }

        return $data;
    }

    protected function processResponse(array $rawResponse, AiRequestInterface $originalRequest): AiResponseInterface
    {
        $response = $this->responseFactory->create();

        try {
            if (isset($rawResponse['error'])) {
                return $this->createErrorResponse(
                    $rawResponse['error']['message'] ?? (string)__('Gemini API error'),
                    0
                );
            }

            if (!isset($rawResponse['candidates']) || !is_array($rawResponse['candidates'])) {
                return $this->createErrorResponse(
                    (string)__('Invalid Gemini API response: missing candidates'),
                    0
                );
            }

            // Check if the response was truncated due to max tokens
            if (isset($rawResponse['candidates'][0]['finishReason']) &&
                $rawResponse['candidates'][0]['finishReason'] === 'MAX_TOKENS') {
                return $this->createErrorResponse(
                    (string)__('Gemini API response was truncated because the max_tokens limit was reached. Consider increasing the max_tokens setting or using a shorter prompt.'),
                    0
                );
            }

            $content = $this->extractContent($rawResponse['candidates']);
            if (empty($content)) {
                return $this->createErrorResponse(
                    (string)__('Empty content received from Gemini API'),
                    0
                );
            }

            $response->setStatus(AiResponseInterface::STATUS_SUCCESS);
            $response->setContent($content);
            $response->setProvider($this->getProvider());
            $response->setModel($originalRequest->getModel() ? : $this->configProvider->getDefaultModel($this->getProvider()));
            $response->setCreatedAt(date('Y-m-d H:i:s'));

            if (isset($rawResponse['usageMetadata'])) {
                $usage = $rawResponse['usageMetadata'];
                $response->setUsageData([
                    'input_tokens'  => $usage['promptTokenCount'] ?? 0,
                    'output_tokens' => $usage['candidatesTokenCount'] ?? 0,
                    'total_tokens'  => $usage['totalTokenCount'] ?? 0,
                ]);
                $response->setPromptTokens($usage['promptTokenCount'] ?? 0);
                $response->setCompletionTokens($usage['candidatesTokenCount'] ?? 0);
            }

            $metadata = [];
            if (isset($rawResponse['modelVersion'])) {
                $metadata['model_version'] = $rawResponse['modelVersion'];
            }
            if (!empty($metadata)) {
                $response->setMetadata($metadata);
            }

            if (isset($rawResponse['candidates'][0]['finishReason'])) {
                $response->setFinishReason($rawResponse['candidates'][0]['finishReason']);
            }

            return $response;

        } catch (\Exception $e) {
            return $this->createErrorResponse(
                sprintf('Gemini response processing error: %s', $e->getMessage()),
                0
            );
        }
    }

    protected function extractContent(array $candidates): string
    {
        if (empty($candidates)) {
            return '';
        }

        $textContent = [];

        $candidate = $candidates[0];
        if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $textContent[] = $part['text'];
                }
            }
        }

        return implode(' ', $textContent);
    }

    private function buildParts(AiRequestInterface $request): array
    {
        $attachments = $request->getAttachments();
        $parts       = [];

        foreach ($attachments as $att) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $att['media_type'],
                    'data'     => $att['data'],
                ],
            ];
        }

        $prompt  = $request->getPrompt();
        $parts[] = [
            'text' => is_array($prompt) ? implode("\n", $prompt) : $prompt,
        ];

        return $parts;
    }

    private function getMaxTokens(AiRequestInterface $request): int
    {
        $requestMaxTokens = $request->getMaxTokens();
        if ($requestMaxTokens !== null && $requestMaxTokens > 0) {
            return $requestMaxTokens;
        }

        $configMaxTokens = $this->configProvider->getMaxTokens();
        if ($configMaxTokens > 0) {
            return $configMaxTokens;
        }

        return 0;
    }

}
