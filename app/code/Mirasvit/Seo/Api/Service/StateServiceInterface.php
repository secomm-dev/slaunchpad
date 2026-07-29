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

interface StateServiceInterface
{
    /**
     * @return bool
     */
    public function isCategoryPage();

    /**
     * @return bool
     */
    public function isLandingPage();

    /**
     * @return bool
     */
    public function isNavigationPage();

    /**
     * @return bool
     */
    public function isProductPage();

    /**
     * @return bool
     */
    public function isCmsPage();

    /**
     * @return bool
     */
    public function isHomePage();

    /**
     * @return bool
     */
    public function isBlogPage();

    /**
     * @return bool
     */
    public function isBrandPage();

    /**
     * @return bool
     */
    public function isAllBrandsPage();

    /**
     * @return false|\Magento\Catalog\Model\Category
     */
    public function getCategory();

    /**
     * @return false|\Magento\Catalog\Model\Product
     */
    public function getProduct();

    /**
     * @return \Magento\Cms\Api\Data\PageInterface|null
     */
    public function getCmsPage();

    /**
     * @return false|\Magento\Framework\DataObject
     */
    public function getFilters();

    /**
     * @return mixed
     */
    public function getBlogPage();

    /**
     * @return mixed
     */
    public function getBrandPage();

    /**
     * @return mixed
     */
    public function getLandingPage();
}
