<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Media;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves portable media values against the current store media base URL.
 */
class UrlResolver
{
    /**
     * @param StoreManagerInterface $storeManager Store context provider.
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * Return an absolute URL while preserving already absolute external media URLs.
     */
    public function resolve(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '/media/')) {
            return $value;
        }

        $relativePath = preg_replace('#^/?media/#', '', $value) ?? $value;
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        return rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');
    }
}
