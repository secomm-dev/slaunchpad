<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Client;

use Secomm\EInvoiceMisa\Model\Config\MisaConfig;

/**
 * Builds MeInvoice query strings from store configuration.
 */
class MisaApiQueryBuilder
{
    public function __construct(
        private readonly MisaConfig $config
    ) {
    }

    public function invoiceWithCodeParam(?int $storeId = null): string
    {
        return $this->config->isInvoiceWithCode($storeId) ? 'true' : 'false';
    }

    public function invoiceCalcuParam(?int $storeId = null): string
    {
        return $this->config->isInvoiceCalculatingMachine($storeId) ? 'true' : 'false';
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function build(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $parts);
    }
}
