<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Response;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Store context DTO — fixed field allowlist (store_code, locale, currency,
 * base_url only; never internal website/group ids).
 */
class StoreDto
{
    /**
     * Assemble the store context DTO.
     *
     * @param StoreInterface $store resolved store view
     * @return mixed[] DTO array
     */
    public function toArray(StoreInterface $store): array
    {
        return [
            'store_code' => (string) $store->getCode(),
            'locale' => (string) $store->getConfig('general/locale/code'),
            'currency' => (string) $store->getDefaultCurrencyCode(),
            'base_url' => rtrim((string) $store->getBaseUrl(), '/'),
        ];
    }
}
