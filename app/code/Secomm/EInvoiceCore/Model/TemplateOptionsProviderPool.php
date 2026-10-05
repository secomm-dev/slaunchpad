<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Secomm\EInvoiceCore\Api\TemplateOptionsProviderInterface;

/**
 * Resolves the template options provider for the configured provider.
 */
class TemplateOptionsProviderPool
{
    /**
     * @param Config $config
     * @param array<string, TemplateOptionsProviderInterface> $providers
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $providers = []
    ) {
    }

    /**
     * Return the options provider for the store's configured provider, or null.
     *
     * @param int|null $storeId
     * @return TemplateOptionsProviderInterface|null
     */
    public function get(?int $storeId = null): ?TemplateOptionsProviderInterface
    {
        $provider = $this->config->getProvider($storeId);

        return $this->providers[$provider] ?? null;
    }
}
