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

interface ProviderConfigInterface
{
    public function getProviderName(): string;

    public function getProviderLabel(): string;

    public function getAvailableModels(): array;

    public function getDefaultModel(): string;

    public function isProviderEnabled(): bool;

    public function getApiKey(): string;

    public function getTimeout(): int;

    public function getMaxTokens(): int;

    public function getTemperature(): float;

    public function getBaseUrl(): string;
}
