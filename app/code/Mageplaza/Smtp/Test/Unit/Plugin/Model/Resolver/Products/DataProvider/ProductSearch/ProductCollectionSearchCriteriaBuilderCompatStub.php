<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch;

// Test-only compatibility stub — required by ProductSearchCriteriaBuilderTest.php.
//
// Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch\ProductCollectionSearchCriteriaBuilder
// (the plugin's around-target, see Mageplaza\Smtp\Plugin\...\ProductSearchCriteriaBuilder::aroundBuild) does not
// exist in the installed magento/module-catalog-graph-ql package: there is no ProductSearch/ subdirectory under
// vendor/magento/module-catalog-graph-ql/Model/Resolver/Products/DataProvider/ (verified via grep — the real class
// there is now Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder). Without this stub, createMock()
// on the real FQCN fails with a ReflectionException before any test runs. aroundBuild() never calls a method on
// $subject, so a bodyless stand-in is sufficient to satisfy the type-hint.
if (!class_exists(ProductCollectionSearchCriteriaBuilder::class, false)) {
    class ProductCollectionSearchCriteriaBuilder
    {
    }
}
