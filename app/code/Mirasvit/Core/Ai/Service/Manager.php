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

use Magento\Framework\ObjectManagerInterface;
use Mirasvit\Core\Ai\Logger\AiLogger;
use Mirasvit\Core\Ai\Model\ConfigProvider;
use Mirasvit\Core\Ai\Api\Data\AiRequestInterface;
use Mirasvit\Core\Ai\Api\Data\AiResponseInterface;

class Manager
{
    private $objectManager;

    private $configProvider;

    private $logger;

    private $providers;

    private $serviceCache   = [];


    public function __construct(
        ObjectManagerInterface $objectManager,
        ConfigProvider         $configProvider,
        AiLogger               $logger,
        array                  $providers = []
    ) {
        $this->objectManager  = $objectManager;
        $this->configProvider = $configProvider;
        $this->logger         = $logger;
        $this->providers      = $providers;
    }

    public function sendRequest(AiRequestInterface $request): AiResponseInterface
    {
        if (!$this->configProvider->isEnabled()) {
            throw new \Exception((string)__('AI services are disabled in configuration'));
        }

        if ($request->getProvider()) {
            return $this->sendRequestToProvider($request, $request->getProvider());
        }

        $defaultProvider = $this->configProvider->getDefaultProvider();
        if (!empty($defaultProvider)) {
            try {
                return $this->sendRequestToProvider($request, $defaultProvider);
            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    'Default provider %s failed, attempting fallback: %s',
                    $defaultProvider,
                    $e->getMessage()
                ));
            }
        }

        return $this->sendRequestWithFallback($request);
    }

    public function sendRequestToProvider(AiRequestInterface $request, string $provider): AiResponseInterface
    {
        $service = $this->getService($provider);

        // Auto-fill provider/model so consumers don't need to replicate Core's
        // resolution chain. AbstractAiService::validateRequest() requires both
        // to be non-empty (via $request->isValid()); without this, any caller
        // that hasn't pre-set them gets "Invalid AI request data".
        if (!$request->getProvider()) {
            $request->setProvider($provider);
        }
        if (!$request->getModel()) {
            $request->setModel($service->getDefaultModel());
        }

        $this->logger->info(sprintf(
            'Sending AI request to provider: %s, Model: %s',
            $provider,
            $request->getModel() ? : 'default'
        ));

        $response = $service->sendRequest($request);

        if ($response->isSuccess()) {
            $this->logger->info(sprintf(
                'AI request completed successfully: Provider=%s, Tokens=%d',
                $provider,
                $response->getTokensUsed()
            ));
        }

        return $response;
    }

    public function getService(string $provider): AbstractAiService
    {
        if (!isset($this->serviceCache[$provider])) {
            $this->serviceCache[$provider] = $this->createService($provider);
        }

        return $this->serviceCache[$provider];
    }

    public function getDefaultService(): AbstractAiService
    {
        $defaultProvider = $this->configProvider->getDefaultProvider();

        if (empty($defaultProvider)) {
            throw new \Exception((string)__('No default AI provider configured'));
        }

        return $this->getService($defaultProvider);
    }

    public function isAvailable(): bool
    {
        if (!$this->configProvider->isEnabled()) {
            return false;
        }

        return count($this->getAvailableProviders()) > 0;
    }

    public function getAvailableProviders(): array
    {
        $availableProviders = [];
        $enabledProviders   = $this->configProvider->getEnabledProviders();

        foreach ($enabledProviders as $provider) {
            if ($this->isProviderHealthy($provider)) {
                $availableProviders[] = $provider;
            }
        }

        usort($availableProviders, function ($a, $b) {
            $priorityA = $this->getProviderPriority($a);
            $priorityB = $this->getProviderPriority($b);

            return $priorityB - $priorityA;
        });

        return $availableProviders;
    }

    public function isProviderAvailable(string $provider): bool
    {
        if (!$this->configProvider->isProviderEnabled($provider)) {
            return false;
        }

        return $this->isProviderHealthy($provider);
    }

    public function getSupportedProviders(): array
    {
        return array_keys($this->providers);
    }


    public function getAvailableModels(): array
    {
        $models           = [];
        $enabledProviders = $this->configProvider->getEnabledProviders();

        foreach ($enabledProviders as $provider) {
            $providerModels = $this->configProvider->getAvailableModels($provider);

            foreach ($providerModels as $modelId => $modelName) {
                $models[$modelId] = [
                    'name'      => $modelName,
                    'provider'  => $provider,
                    'full_name' => sprintf('%s (%s)', $modelName, ucfirst($provider)),
                ];
            }
        }

        return $models;
    }

    public function validateRequest(AiRequestInterface $request): bool
    {
        $provider = $this->getRecommendedProvider($request);

        if (!$provider) {
            throw new \InvalidArgumentException((string)__('No available AI provider for request validation'));
        }

        try {
            $service = $this->getService($provider);

            return $service->validateRequest($request);

        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                (string)__('Request validation failed: %1', $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function getRecommendedProvider(AiRequestInterface $request): ?string
    {
        if ($request->getProvider() && $this->isProviderAvailable($request->getProvider())) {
            return $request->getProvider();
        }

        if ($request->getModel()) {
            $providers = $this->getAvailableProviders();

            foreach ($providers as $provider) {
                try {
                    $service = $this->getService($provider);
                    if (in_array($request->getModel(), $service->getSupportedModels())) {
                        return $provider;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $defaultProvider = $this->configProvider->getDefaultProvider();
        if ($this->isProviderAvailable($defaultProvider)) {
            return $defaultProvider;
        }

        $availableProviders = $this->getAvailableProviders();

        return reset($availableProviders) ? : null;
    }



    private function sendRequestWithFallback(AiRequestInterface $request): AiResponseInterface
    {
        $availableProviders = $this->getAvailableProviders();
        $lastException      = null;

        foreach ($availableProviders as $provider) {
            try {
                $this->logger->info(sprintf('Attempting fallback provider: %s', $provider));

                return $this->sendRequestToProvider($request, $provider);

            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    'Fallback provider %s failed: %s',
                    $provider,
                    $e->getMessage()
                ));

                $lastException = $e;
            }
        }

        $errorMessage = (string)__('All AI providers failed. Last error: %1',
            $lastException ? $lastException->getMessage() : 'Unknown error'
        );

        $this->logger->error($errorMessage);
        throw new \Exception($errorMessage);
    }

    private function createService(string $provider): AbstractAiService
    {
        if (empty($provider)) {
            $provider = $this->configProvider->getDefaultProvider();

            if (empty($provider)) {
                throw new \InvalidArgumentException((string)__('No default AI provider configured'));
            }
        }

        if (!$this->isProviderSupported($provider)) {
            throw new \InvalidArgumentException(
                (string)__('Unsupported AI provider: %1. Supported providers: %2',
                    $provider,
                    implode(', ', $this->getSupportedProviders())
                )
            );
        }

        $serviceClass = $this->getProviderServiceClass($provider);

        try {
            $service = $this->objectManager->create($serviceClass);

            if (!$service instanceof AbstractAiService) {
                throw new \RuntimeException(
                    (string)__('Service class %1 must extend %2', $serviceClass, AbstractAiService::class)
                );
            }

            $this->logger->debug(sprintf(
                'AI service created successfully: Provider=%s, Class=%s',
                $provider,
                $serviceClass
            ));

            return $service;

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Failed to create AI service for provider %s: %s',
                $provider,
                $e->getMessage()
            ));

            throw new \RuntimeException(
                (string)__('Failed to create AI service for provider %1: %2', $provider, $e->getMessage()),
                0,
                $e
            );
        }
    }

    private function isProviderSupported(string $provider): bool
    {
        return array_key_exists($provider, $this->providers);
    }

    private function getProviderServiceClass(string $provider): string
    {
        return $this->providers[$provider]['class'] ?? '';
    }

    private function getProviderPriority(string $provider): int
    {
        return $this->providers[$provider]['priority'] ?? 0;
    }

    private function isProviderHealthy(string $provider): bool
    {
        try {
            if (!$this->isProviderSupported($provider)) {
                return false;
            }

            $service = $this->getService($provider);
            return $service->isAvailable();

        } catch (\Exception $e) {
            return false;
        }
    }


}
