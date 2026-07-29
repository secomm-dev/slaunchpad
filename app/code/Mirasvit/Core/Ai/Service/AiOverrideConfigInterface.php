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

/**
 * Implemented by modules that want to override Core's AI defaults
 * (provider, model, API key) for their own AI requests.
 *
 * Pass an implementation to RequestBuilder::applyOverrides() before
 * calling Manager::sendRequest(). Each non-null getter is set on the
 * Request; null values are left untouched, so Manager and the
 * provider's prepareClient() resolve them from Core's per-provider
 * configuration naturally.
 */
interface AiOverrideConfigInterface
{
    /**
     * True when the consuming module is overriding Core's defaults.
     * When false, all overrides are skipped — the request goes through
     * Core unchanged.
     */
    public function isOverrideEnabled(): bool;

    /**
     * Provider id (e.g. 'openai', 'claude') the module wants to use,
     * or null to defer to Core's default_provider.
     */
    public function getOverrideProvider(): ?string;

    /**
     * Model id for the override provider, or null to defer to Core's
     * configured default model for that provider.
     */
    public function getOverrideModel(): ?string;

    /**
     * Decrypted API key for the override provider, or null to defer to
     * Core's per-provider key configuration.
     */
    public function getOverrideApiKey(): ?string;
}
