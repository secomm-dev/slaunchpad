<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\InvoiceDocumentServiceInterface;

/**
 * Resolves the post-publish document service for the configured provider.
 */
class InvoiceDocumentServicePool
{
    /**
     * @param Config $config
     * @param array<string, InvoiceDocumentServiceInterface> $services
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $services = []
    ) {
    }

    /**
     * Return the document service for the store's configured provider.
     *
     * @param int|null $storeId
     * @return InvoiceDocumentServiceInterface
     * @throws LocalizedException
     */
    public function get(?int $storeId = null): InvoiceDocumentServiceInterface
    {
        $provider = $this->config->getProvider($storeId);

        if (!isset($this->services[$provider])) {
            throw new LocalizedException(
                __('EInvoice document service for provider "%1" is not registered.', $provider)
            );
        }

        return $this->services[$provider];
    }
}
