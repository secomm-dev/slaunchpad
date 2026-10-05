<?php
/**
 * TASK-KMJV5Q (SLP-291): the copyright bar renders inside full-page-cached
 * pages — clean block_html + full_page so an admin save is visible on the
 * storefront immediately.
 *
 * @copyright Copyright (c) 2026 Secomm. (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\CmsContent\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

final class InvalidateFooterLegalConfig implements ObserverInterface
{
    public function __construct(
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->cacheTypeList->cleanType('block_html');
        $this->cacheTypeList->cleanType('full_page');
    }
}
