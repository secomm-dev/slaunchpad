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


namespace Mirasvit\SeoAi\Service\Context;


use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoAi\Model\ConfigProvider;

class ProductContext extends AbstractContext
{
    const IGNORED_FIELDS = [
        'status', 'sku', 'price', 'cost', 'visibility', 'new', 'sale', 'shipment_type', 'image', 'small_image', 'thumbnail',
        'url_key', 'msrp_display_actual_price_type', 'price_view', 'page_layout', 'custom_design', 'custom_layout',
        'gift_message_available', 'image_label', 'small_image_label', 'thumbnail_label', 'tax_class_id', 'options_container',
        'quantity_and_stock_status', 'swatch_image', 'special_price', 'special_to_date', 'special_from_date',
    ];

    private $productRepository;

    private $priceCurrency;

    public function __construct(
        ProductRepository $productRepository,
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager,
        ConfigProvider $configProvider,
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager
    ) {
        $this->productRepository = $productRepository;
        $this->priceCurrency     = $priceCurrency;

        parent::__construct($storeManager, $configProvider, $objectManager, $moduleManager);
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getContext(?int $entityId = null, ?array $params = null): array
    {
        $context = parent::getContext($entityId, $params);

        try {
            $product = $this->productRepository->getById(
                $entityId,
                false,
                $this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException $e) {
            return $context;
        }

        $attributes = $product->getAttributes();

        if (is_null($attributes)) {
            return $context;
        }

        $productData = [];

        foreach ($attributes as $attribute) {
            try {
                $value = $attribute->getFrontend()->getValue($product);
            } catch (\Exception $e) {
                // some attributes can throw exceptions so we ignore them
                // example: attribute added by 3rd-party module, module removed but the attribute still present
                continue;
            }

            if ($value instanceof Phrase) {
                $value = (string)$value;
            } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                $value = $this->priceCurrency->convertAndFormat((float)$value, false);
            }

            if (is_string($value) && strlen(trim($value)) && $attribute->getStoreLabel()) {
                if (in_array($attribute->getAttributeCode(), self::IGNORED_FIELDS)) {
                    continue;
                }
                if ($value == "No") { //fix me. Multi lang
                    continue;
                }
                $value = $this->clear($value);
                if (trim($value) == "") {
                    continue;
                }
                $productData[] = [
                    'id'    => 'product.' . $attribute->getAttributeCode(),
                    'code'  => $attribute->getAttributeCode(),
                    'label' => $attribute->getStoreLabel(),
                    'value' => $value,
                ];
            }
        }

        $context['product'] = $productData;

        return $context;
    }
}
