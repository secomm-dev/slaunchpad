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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as EntityAttributeOptionCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoFilter\Model\ConfigProvider;

class Context
{
    private $productResource;

    private $storeManager;

    private $attributeOptionCollectionFactory;

    private $urlBuilder;

    private $registry;

    private $configProvider;

    /** @var \Magento\Framework\App\RequestInterface */
    private $request;

    private $attributeCache = [];

    public function __construct(
        ProductResource $productResource,
        StoreManagerInterface $storeManager,
        EntityAttributeOptionCollectionFactory $entityAttributeOptionCollectionFactory,
        UrlInterface $urlBuilder,
        Registry $registry,
        RequestInterface $request,
        ConfigProvider $configProvider
    ) {
        $this->productResource                  = $productResource;
        $this->storeManager                     = $storeManager;
        $this->attributeOptionCollectionFactory = $entityAttributeOptionCollectionFactory;
        $this->urlBuilder                       = $urlBuilder;
        $this->registry                         = $registry;
        $this->request                          = $request;
        $this->configProvider                   = $configProvider;
    }


    public function getStoreId(): int
    {
        return (int)$this->storeManager->getStore()->getId();
    }

    public function getDefaultStoreId(): int
    {
        return (int)$this->storeManager->getDefaultStoreView()->getId();
    }


    public function getAttribute(string $code): ?\Magento\Catalog\Model\ResourceModel\Eav\Attribute
    {
        if (isset($this->attributeCache[$code])) {
            return $this->attributeCache[$code];
        }

        $attribute = $this->productResource->getAttribute($code);
        $this->attributeCache[$code] = $attribute ?: null;

        return $this->attributeCache[$code];
    }

    public function isDecimalAttribute(string $attribute): bool
    {
        $attr = $this->getAttribute($attribute);

        return $attr && ($attr->getFrontendInput() == 'price' || $this->configProvider->isDisplayModeSlider($attribute));
    }

    public function getAttributeOption(int $attributeId, int $optionId, ?int $storeId = null): ?\Magento\Eav\Model\Entity\Attribute\Option
    {
        if ($this->configProvider->getAliasGenerationMode() == ConfigProvider::REWRITES_DEFAULT_STORE) {
            $storeId = $this->getDefaultStoreId();
        } else {
            $storeId = $storeId ?? $this->getStoreId();
        }
        /** @var \Magento\Eav\Model\Entity\Attribute\Option $item */
        $item = $this->attributeOptionCollectionFactory->create()
            ->setStoreFilter($storeId, true)
            ->setAttributeFilter($attributeId)
            ->setIdFilter($optionId)
            ->getFirstItem();

        return $item->getId() ? $item : null;
    }

    public function getUrlBuilder(): UrlInterface
    {
        return $this->urlBuilder;
    }

    public function getCurrentCategory(): ?\Magento\Catalog\Model\Category
    {
        $category = $this->registry->registry('current_category');

        return $category ? $category : null;
    }

    public function getRequest(): \Magento\Framework\App\RequestInterface
    {
        return $this->request;
    }
}
