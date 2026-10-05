<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Magento\Framework\Exception\LocalizedException;
use Secomm\EInvoiceCore\Api\IssueRequestBuilderInterface;

/**
 * Resolves the issue request builder for the configured provider.
 */
class IssueRequestBuilderPool
{
    /**
     * @param Config $config
     * @param array $builders
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $builders = []
    ) {
    }

    /**
     * Return request builder for the store's configured provider.
     *
     * @param int|null $storeId
     * @return IssueRequestBuilderInterface
     * @throws LocalizedException
     */
    public function get(?int $storeId = null): IssueRequestBuilderInterface
    {
        $provider = $this->config->getProvider($storeId);

        if (!isset($this->builders[$provider])) {
            throw new LocalizedException(
                __('EInvoice request builder for provider "%1" is not registered.', $provider)
            );
        }

        return $this->builders[$provider];
    }
}
