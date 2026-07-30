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


namespace Mirasvit\Seo\Plugin\Adminhtml;


use Mirasvit\Seo\Model\Config\ProductUrlTemplateConfig;

class ProductUrlKeyAfterVisibilityChangePlugin
{
    private $productUrlTemplateConfig;

    public function __construct(ProductUrlTemplateConfig $productUrlTemplateConfig)
    {
        $this->productUrlTemplateConfig  = $productUrlTemplateConfig;
    }

    public function afterDoesEntityHaveOverriddenUrlKeyForStore($subject, $result, $storeId, $entityId, $entityType)
    {
        if ($this->productUrlTemplateConfig->getProductUrlKey((int)$storeId)) {
            return false; // to restore url rewrites after product visibility changed
        }

        return $result;
    }
}
