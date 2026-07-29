<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoMarkup\Model\Config;

use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoMarkup\Model\Config;

/**
 * Answer-Engine Optimization (AEO) settings.
 *
 * When enabled, the structured-data emitters stitch their JSON-LD nodes into one connected
 * graph by adding stable @id values and cross-references (Product -> brand / seller -> Organization),
 * so AI answer engines can assemble a single confident entity from the page. When disabled
 * (the default), every emitter produces byte-for-byte the same output it does today.
 */
class AeoConfig extends Config
{
    public const ID_ORGANIZATION = '#organization';
    public const ID_WEBSITE      = '#website';
    public const ID_PRODUCT      = '#product';
    public const ID_OFFER        = '#offer';
    public const ID_BREADCRUMB   = '#breadcrumb';

    public function isAeoEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/aeo/is_aeo_enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
