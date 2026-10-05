<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

/**
 * Provides invoice template options for admin selection (implemented per provider).
 *
 * @api
 * @since 1.0.0
 */
interface TemplateOptionsProviderInterface
{
    /**
     * Selectable template options for the store.
     *
     * @param int|null $storeId
     * @return array<int, array{value: string, label: string}>
     */
    public function getOptions(?int $storeId = null): array;

    /**
     * Default template id configured for the store.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDefaultId(?int $storeId = null): string;
}
