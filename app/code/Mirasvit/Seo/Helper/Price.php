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

namespace Mirasvit\Seo\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Tax\Model\Config;

class Price
{
    private $scopeConfig;

    private $productRepository;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ProductRepository    $productRepository
    ) {
        $this->scopeConfig       = $scopeConfig;
        $this->productRepository = $productRepository;
    }

    public function getFinalPrice(ProductInterface $product, Store $store): string
    {
        $priceAmount = $product->getPriceInfo()
            ->getPrice(FinalPrice::PRICE_CODE)
            ->getAmount();

        $taxesType = (int)$this->scopeConfig->getValue(
            'tax/display/type',
            ScopeInterface::SCOPE_STORES,
            $store
        );

        if (in_array($taxesType, [Config::DISPLAY_TYPE_INCLUDING_TAX, Config::DISPLAY_TYPE_BOTH])) {
            $finalPrice = $priceAmount->getValue();
        } else {
            $finalPrice = $priceAmount->getBaseAmount();
        }

        if ($product->getTypeId() === 'grouped') {
            $finalPrice = $this->getGroupedMinPrice($product, $store);
        }

        return number_format((float)$finalPrice, 2, '.', '');
    }

    private function getGroupedMinPrice(ProductInterface $product, Store $store): float
    {
        $typeInstance = $product->getTypeInstance();
        $childrenIds  = $typeInstance->getChildrenIds($product->getId());
        $minPrice     = 0;

        foreach (array_values($childrenIds)[0] as $childId) {
            $child      = $this->productRepository->getById($childId);
            $childPrice = $this->getFinalPrice($child, $store);
            $minPrice   = $minPrice == 0 ? $childPrice : min($minPrice, $childPrice);
        }

        return (float)$minPrice;
    }
}
