<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\CommercialDiscountRequestBuilderInterface;

/**
 * Resolves commercial discount request builder for the configured provider.
 */
class CommercialDiscountRequestBuilderPool
{
    /**
     * @param array<string, CommercialDiscountRequestBuilderInterface> $builders
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $builders = []
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function get(?int $storeId = null): CommercialDiscountRequestBuilderInterface
    {
        $provider = $this->config->getProvider($storeId);

        if (!isset($this->builders[$provider])) {
            throw new LocalizedException(
                __('EInvoice commercial discount builder for provider "%1" is not registered.', $provider)
            );
        }

        return $this->builders[$provider];
    }
}
