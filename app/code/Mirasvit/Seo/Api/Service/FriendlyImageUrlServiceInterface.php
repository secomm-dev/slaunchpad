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



namespace Mirasvit\Seo\Api\Service;

interface FriendlyImageUrlServiceInterface
{
    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param string $fileName
     * @return string
     */
    public function getFriendlyImageName($product, $fileName);

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param int|null $storeId
     * @return string
     */
    public function getFriendlyImageAlt($product, $storeId = null);

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param int|null $storeId
     * @return string
     */
    public function getFriendlyImageTitle($product, $storeId = null);
}
