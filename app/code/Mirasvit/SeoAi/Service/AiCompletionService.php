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
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoAi\Service;

use Mirasvit\Core\Ai\Model\RequestFactory;
use Mirasvit\Core\Ai\Service\Manager as AiManager;
use Mirasvit\SeoAi\Model\ConfigProvider;

class AiCompletionService
{
    private $configProvider;

    private $aiManager;

    private $requestFactory;

    public function __construct(
        ConfigProvider $configProvider,
        AiManager      $aiManager,
        RequestFactory $requestFactory
    ) {
        $this->configProvider = $configProvider;
        $this->aiManager      = $aiManager;
        $this->requestFactory = $requestFactory;
    }

    public function isAvailable(): bool
    {
        if (!$this->configProvider->getApiKey()) {
            return false;
        }

        if ($this->configProvider->useCoreAi()) {
            return $this->aiManager->isProviderAvailable($this->configProvider->getProvider());
        }

        return true;
    }

    public function getCompletion(string $prompt): ?string
    {
        if (!$this->configProvider->isHelperEnabled()) {
            return null;
        }

        $apiKey = $this->configProvider->getApiKey();
        if (!$apiKey) {
            throw new \RuntimeException((string)__('API key is not configured.'));
        }

        $request = $this->requestFactory->create();
        $request->setPrompt($prompt);
        $request->setProvider($this->configProvider->getProvider());
        $request->setModel($this->configProvider->getModel());

        if (!$this->configProvider->useCoreAi()) {
            $request->setApiKey($apiKey);
        }

        $response = $this->aiManager->sendRequest($request);

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                (string)__('AI request failed: %1', $response->getErrorMessage())
            );
        }

        return $response->getContent();
    }
}
