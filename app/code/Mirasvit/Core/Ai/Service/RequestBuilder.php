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

use Mirasvit\Core\Ai\Api\Data\AiRequestInterface;

/**
 * Helper for consumer modules to apply per-module AI overrides to a
 * request without replicating Core's default-resolution chain.
 *
 * Usage:
 *
 *   $request = $this->requestFactory->create();
 *   $request->setSystemPrompt(...);
 *   $request->setPrompt(...);
 *   $this->requestBuilder->applyOverrides($request, $this->moduleConfig);
 *   $response = $this->manager->sendRequest($request);
 */
class RequestBuilder
{
    /**
     * Apply per-module overrides to an AI request. Each non-null value
     * from the override config is set on the Request; null values are
     * left untouched so Manager's auto-fill and AbstractAiService's
     * prepareClient resolve them from Core's per-provider configuration.
     */
    public function applyOverrides(AiRequestInterface $request, AiOverrideConfigInterface $override): void
    {
        if (!$override->isOverrideEnabled()) {
            return;
        }

        $provider = $override->getOverrideProvider();
        if ($provider !== null && $provider !== '') {
            $request->setProvider($provider);
        }

        $model = $override->getOverrideModel();
        if ($model !== null && $model !== '') {
            $request->setModel($model);
        }

        $apiKey = $override->getOverrideApiKey();
        if ($apiKey !== null && $apiKey !== '') {
            $request->setApiKey($apiKey);
        }
    }
}
