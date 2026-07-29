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



namespace Mirasvit\SeoSitemap\Repository\Provider\Magento;

use Magento\Framework\DataObject;
use Magento\Sitemap\Helper\Data as DataHelper;
use Magento\Sitemap\Model\ResourceModel\Catalog\ProductFactory;
use Mirasvit\Seo\Service\Alternate\ProductStrategy;
use Mirasvit\Seo\Service\Config\AlternateConfig;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class ProductProvider extends AbstractProvider
{
    const KEY         = 'products';
    const MODULE_NAME = 'Magento_Catalog';
    const TITLE       = 'Products';

    private $dataHelper;

    private $productFactory;

    private $productStrategy;

    private $alternateConfig;

    public function __construct(
        DataHelper $sitemapData,
        ProductFactory $productFactory,
        ProductStrategy $productStrategy,
        AlternateConfig $alternateConfig
    ) {
        $this->dataHelper     = $sitemapData;
        $this->productFactory = $productFactory;
        $this->productStrategy = $productStrategy;
        $this->alternateConfig = $alternateConfig;
    }

    /**
     * @param int $storeId
     * @return array|DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Db_Statement_Exception
     */
    public function initSitemapItem($storeId)
    {
        return new DataObject([
            'changefreq' => $this->dataHelper->getProductChangefreq($storeId),
            'priority'   => $this->dataHelper->getProductPriority($storeId),
            'collection' => $this->getProductItems($storeId),
        ]);
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getItems($storeId)
    {
        return [];
    }

    private function getProductItems($storeId)
    {
        $products = $this->productFactory->create()->getCollection($storeId);

        if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
            foreach ($products as $product) {
                $alternates = $this->productStrategy->getAlternateUrl([], (int)$product->getId(), (int)$storeId);
                $product->setAlternates($alternates);
            }
        }

        return $products;
    }
}
