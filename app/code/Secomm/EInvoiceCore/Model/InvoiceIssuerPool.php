<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\InvoiceIssuerInterface;

/**
 * Resolves the invoice issuer implementation for the configured provider.
 */
class InvoiceIssuerPool
{
    /**
     * @param Config $config
     * @param array $issuers
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $issuers = []
    ) {
    }

    /**
     * Return issuer for the store's configured provider.
     *
     * @param int|null $storeId
     * @return InvoiceIssuerInterface
     * @throws LocalizedException
     */
    public function get(?int $storeId = null): InvoiceIssuerInterface
    {
        $provider = $this->config->getProvider($storeId);

        if (!isset($this->issuers[$provider])) {
            throw new LocalizedException(
                __('EInvoice provider "%1" is not registered.', $provider)
            );
        }

        return $this->issuers[$provider];
    }
}
