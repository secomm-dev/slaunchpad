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

namespace Mirasvit\SeoToolbar\DataProvider\Criteria;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Config\CurrentPageProductsInterface;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Repository\ExtenderRepository;
use Mirasvit\SeoToolbar\Api\Data\DataProviderItemInterface;

class RsCriteria extends AbstractCriteria
{
    private const LABEL           = 'Rich snippets';
    private const EXTENDERS_LABEL = 'Applied extenders';

    private $request;

    private $registry;

    private $storeManager;

    private $productRepository;

    private $extenderRepository;

    public function __construct(
        RequestInterface           $request,
        Registry                   $registry,
        StoreManagerInterface      $storeManager,
        ProductRepositoryInterface $productRepository,
        ExtenderRepository         $extenderRepository
    ) {
        $this->request            = $request;
        $this->registry           = $registry;
        $this->storeManager       = $storeManager;
        $this->productRepository  = $productRepository;
        $this->extenderRepository = $extenderRepository;
    }

    public function handle(string $content): array
    {
        $validateUrl = 'https://search.google.com/structured-data/testing-tool#url=' . $this->request->getUriString();

        $extendersNote = $this->getExtendersNote();

        return $this->getItem(
            self::LABEL,
            DataProviderItemInterface::STATUS_NONE,
            empty($extendersNote) ? '' : self::EXTENDERS_LABEL,
            $extendersNote,
            '<a target="_blank" rel="nofollow" href="' . $validateUrl . '">Validate</a>'
        );
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function getExtendersNote(): string
    {
        $note             = '';
        $products         = [];
        $offers           = [];
        $productExtenders = [];
        $offerExtenders   = [];

        $productCollection = $this->registry->registry(CurrentPageProductsInterface::PRODUCT_COLLECTION);

        if (isset($productCollection)) {
            $products = $productCollection->getItems();
            $offers   = $products;
        }

        $product = $this->registry->registry('current_product');

        if (!isset($product)) {
            $product = $this->registry->registry('product');
        }

        if (isset($product)) {
            $products[] = $product;

            switch ($product->getTypeId()) {
                case Configurable::TYPE_CODE:
                    $child = $product->getTypeInstance()->getUsedProductCollection($product)
                        ->addAttributeToSelect('visibility');
                    foreach ($child as $item) {
                        $offers[] = $item;
                    }

                    break;
                case Grouped::TYPE_CODE:
                    $childrenIds = $product->getTypeInstance()->getChildrenIds($product->getId());
                    foreach (array_values($childrenIds)[0] as $childId) {
                        $offers[] = $this->productRepository->getById($childId);
                    }

                    break;
                default:
                    $offers = $products;

                    break;
            }
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        foreach ($products as $product) {
            $extenders = $this->extenderRepository
                ->getListForProduct($product, ExtenderInterface::PRODUCT_TYPE, $storeId);
            foreach ($extenders as $extender) {
                $productExtenders[$extender->getExtenderId()] = $extender->getName();
            }
        }

        foreach ($offers as $offer) {
            $extenders = $this->extenderRepository
                ->getListForProduct($offer, ExtenderInterface::OFFER_TYPE, $storeId);
            foreach ($extenders as $extender) {
                $offerExtenders[$extender->getExtenderId()] = $extender->getName();
            }
        }

        if (count($productExtenders)) {
            $note .= 'Product:' . PHP_EOL;
            foreach ($productExtenders as $extenderName) {
                $note .= ' - ' . $extenderName . PHP_EOL;
            }
        }

        if (count($offerExtenders)) {
            $note .= 'Offer:' . PHP_EOL;
            foreach ($offerExtenders as $extenderName) {
                $note .= ' - ' . $extenderName . PHP_EOL;
            }
        }

        return $note;
    }
}
