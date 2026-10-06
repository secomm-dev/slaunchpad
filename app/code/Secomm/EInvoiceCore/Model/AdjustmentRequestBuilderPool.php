<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\AdjustmentRequestBuilderInterface;

/**
 * Resolves the adjustment request builder for the configured provider.
 */
class AdjustmentRequestBuilderPool
{
    /**
     * @param Config $config
     * @param array<string, AdjustmentRequestBuilderInterface> $builders
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $builders = []
    ) {
    }

    /**
     * Return adjustment builder for the store's configured provider.
     *
     * @param int|null $storeId
     * @return AdjustmentRequestBuilderInterface
     * @throws LocalizedException
     */
    public function get(?int $storeId = null): AdjustmentRequestBuilderInterface
    {
        $provider = $this->config->getProvider($storeId);

        if (!isset($this->builders[$provider])) {
            throw new LocalizedException(
                __('EInvoice adjustment builder for provider "%1" is not registered.', $provider)
            );
        }

        return $this->builders[$provider];
    }
}
