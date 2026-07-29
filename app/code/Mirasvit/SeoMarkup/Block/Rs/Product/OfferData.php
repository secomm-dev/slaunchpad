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

namespace Mirasvit\SeoMarkup\Block\Rs\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Shipping\Model\Config as ShippingConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Tax\Model\Config;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;
use Mirasvit\SeoMarkup\Model\Config as MirasvitConfig;
use Mirasvit\SeoMarkup\Repository\ExtenderRepository;
use Mirasvit\SeoMarkup\Service\SnippetService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OfferData
{
    const PRODUCT_PRICES_INCLUDING_TAX = 2;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var ?StoreInterface
     */
    private $store;

    /**
     * @var ?int
     */
    private $storeId;

    private $productConfig;

    private $templateEngineService;

    private $paymentConfig;

    private $scopeConfig;

    private $shippingConfig;

    private $extenderRepository;

    private $snippetService;

    public function __construct(
        ProductConfig                  $productConfig,
        TemplateEngineServiceInterface $templateEngineService,
        PaymentConfig                  $paymentConfig,
        ScopeConfigInterface           $scopeConfig,
        ShippingConfig                 $shippingConfig,
        ExtenderRepository             $extenderRepository,
        SnippetService                 $snippetService
    ) {
        $this->productConfig         = $productConfig;
        $this->templateEngineService = $templateEngineService;
        $this->paymentConfig         = $paymentConfig;
        $this->scopeConfig           = $scopeConfig;
        $this->shippingConfig        = $shippingConfig;
        $this->extenderRepository    = $extenderRepository;
        $this->snippetService        = $snippetService;
    }

    public function getData(ProductInterface $product, StoreInterface $store, bool $dry = false): array
    {
        $this->product = $dry ? $product : $product->load($product->getId());
        $this->store   = $store;

        $isPriceEnabled = $this->productConfig->isPriceEnabled($this->getStoreId());
        $currencyCode   = $isPriceEnabled ? $this->store->getCurrentCurrencyCode() : '';
        $finalPrice     = $isPriceEnabled ? $this->getFinalPrice() : 0.0;

        $values = [
            '@type'                   => 'Offer',
            'url'                     => $this->product->getVisibility() != 1 ? $this->product->getProductUrl() : false,
            'price'                   => $isPriceEnabled ? number_format($finalPrice, 2, '.', '') : false,
            'priceCurrency'           => $isPriceEnabled ? $currencyCode : false,
            'priceSpecification'      => $isPriceEnabled ? $this->getPriceSpecification($finalPrice, $currencyCode) : false,
            'priceValidUntil'         => $isPriceEnabled ? $this->getPriceValidUntil() : false,
            'availability'            => $this->getOfferAvailability(),
            'itemCondition'           => $this->getOfferItemCondition(),
            'acceptedPaymentMethod'   => $this->getOfferAcceptedPaymentMethods(),
            'availableDeliveryMethod' => $this->getOfferAvailableDeliveryMethods(),
            'sku'                     => $this->product->getSku(),
            'gtin'                    => $this->getGtin(),
        ];

        $extenders = $this->extenderRepository
            ->getListForProduct($product, ExtenderInterface::OFFER_TYPE, $this->getStoreId());
        foreach ($extenders as $extender) {
            $snippetArray = $this->snippetService->renderSnippetVariables($extender->getSnippetArray());
            $values       = $this->snippetService->extendRichSnippet($values, $snippetArray, $extender->getOverride());
        }

        return array_filter($values);
    }

    public function isIncludingTax(): bool
    {
        return in_array(
            $this->scopeConfig->getValue(
                'tax/display/type',
                ScopeInterface::SCOPE_STORES,
                $this->store
            ),
            [Config::DISPLAY_TYPE_INCLUDING_TAX, Config::DISPLAY_TYPE_BOTH]
        );
    }

    protected function getOfferAvailableDeliveryMethods(): ?array
    {
        if (!$this->productConfig->isAvailableDeliveryMethodEnabled($this->getStoreId())) {
            return null;
        }

        if ($activeDeliveryMethods = $this->getActiveDeliveryMethods()) {
            return $activeDeliveryMethods;
        }

        return null;
    }

    private function getOfferAvailability(): ?string
    {
        if (!$this->productConfig->isAvailabilityEnabled($this->getStoreId())) {
            return null;
        }

        $productAvailability = method_exists($this->product, 'isAvailable')
            ? $this->product->isAvailable()
            : $this->product->isInStock();

        if ($productAvailability) {
            return MirasvitConfig::HTTP_SCHEMA_ORG . '/InStock';
        } else {
            return MirasvitConfig::HTTP_SCHEMA_ORG . '/OutOfStock';
        }
    }

    private function getOfferItemCondition(): ?string
    {
        $storeId       = $this->getStoreId();
        $conditionType = $this->productConfig->getItemConditionType($storeId);

        if (!$conditionType) {
            return null;
        }

        if ($conditionType == ProductConfig::ITEM_CONDITION_NEW_ALL) {
            return MirasvitConfig::HTTP_SCHEMA_ORG . '/NewCondition';
        } elseif ($conditionType == ProductConfig::ITEM_CONDITION_MANUAL) {
            $attribute      = $this->productConfig->getItemConditionAttribute($storeId);
            $conditionValue = $this->templateEngineService->render("[product_$attribute]");

            if (!$conditionValue) {
                return null;
            }

            switch ($conditionValue) {
                case $this->productConfig->getItemConditionAttributeValueNew($storeId):
                    return MirasvitConfig::HTTP_SCHEMA_ORG . '/NewCondition';

                case $this->productConfig->getItemConditionAttributeValueUsed($storeId):
                    return MirasvitConfig::HTTP_SCHEMA_ORG . '/UsedCondition';

                case $this->productConfig->getItemConditionAttributeValueRefurbished($storeId):
                    return MirasvitConfig::HTTP_SCHEMA_ORG . '/RefurbishedCondition';

                case $this->productConfig->getItemConditionAttributeValueDamaged($storeId):
                    return MirasvitConfig::HTTP_SCHEMA_ORG . '/DamagedCondition';
            }
        }

        return null;
    }

    private function getOfferAcceptedPaymentMethods(): ?array
    {
        if (!$this->productConfig->isAcceptedPaymentMethodEnabled($this->getStoreId())) {
            return null;
        }

        if ($activePaymentMethods = $this->getActivePaymentMethods()) {
            return $activePaymentMethods;
        }

        return null;
    }

    private function getActivePaymentMethods(): array
    {
        $payments = $this->paymentConfig->getActiveMethods();
        $methods  = [];
        foreach ($payments as $paymentCode => $paymentMethod) {
            if (!is_string($paymentCode)) {
                continue;
            }

            if (strpos($paymentCode, 'paypal') !== false) {
                $methods[] = 'http://purl.org/goodrelations/v1#PayPal';
            }

            if (strpos($paymentCode, 'googlecheckout') !== false) {
                $methods[] = 'http://purl.org/goodrelations/v1#GoogleCheckout';
            }

            if (strpos($paymentCode, 'cash') !== false) {
                $methods[] = 'http://purl.org/goodrelations/v1#Cash';
            }

            if ($paymentCode == 'ccsave') {
                if ($existingMethods = $this->getActivePaymentCCTypes()) {
                    $methods = array_merge($methods, $existingMethods);
                }
            }
        }

        return array_unique($methods);
    }

    private function getActivePaymentCCTypes(): ?array
    {
        $methods    = [];
        $allMethods = [
            'AE'  => 'AmericanExpress',
            'VI'  => 'VISA',
            'MC'  => 'MasterCard',
            'DI'  => 'Discover',
            'JCB' => 'JCB',
        ];

        $ccTypes = $this->scopeConfig->getValue(
            'payment/ccsave/cctypes',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->store
        );

        if ($ccTypes) {
            $list = explode(',', $ccTypes);

            foreach ($list as $value) {
                if (isset($allMethods[$value])) {
                    $methods[] = 'http://purl.org/goodrelations/v1#' . $allMethods[$value];
                }
            }

            return $methods;
        }

        return null;
    }

    private function getActiveDeliveryMethods(): array
    {
        $methods = [];

        $allMethods = [
            'flatrate'     => 'DeliveryModeFreight',
            'freeshipping' => 'DeliveryModeFreight',
            'tablerate'    => 'DeliveryModeFreight',
            'dhl'          => 'DHL',
            'fedex'        => 'FederalExpress',
            'ups'          => 'UPS',
            'usps'         => 'DeliveryModeMail',
            'dhlint'       => 'DHL',
        ];

        $deliveryMethods = $this->shippingConfig->getActiveCarriers();
        foreach (array_keys($deliveryMethods) as $code) {
            if (isset($allMethods[$code])) {
                $methods[] = 'http://purl.org/goodrelations/v1#' . $allMethods[$code];
            }
        }

        return array_unique($methods);
    }

    private function getGtin(): string
    {
        return $this->productConfig->getGtin8Attribute($this->getStoreId())
            ? (string)$this->product->getData($this->productConfig->getGtin8Attribute($this->getStoreId()))
            : '';
    }

    private function getPriceValidUntil(): ?string
    {
        if (!$this->productConfig->isPriceValidUntilEnabled($this->getStoreId())) {
            return null;
        }

        $specialToDate = $this->templateEngineService->render(
            '[product_special_to_date]',
            ['product' => $this->product]
        );

        if (strtotime($specialToDate) > time()) {
            return date("Y-m-d ", strtotime($specialToDate));
        } else {
            return '2030-01-01';
        }
    }

    private function getPriceSpecification(float $finalPrice, string $currencyCode): ?array
    {
        $regularPrice = $this->getRegularPrice();

        if ($regularPrice <= $finalPrice) {
            return null;
        }

        return [
            [
                '@type'     => 'UnitPriceSpecification',
                'priceType' => MirasvitConfig::HTTP_SCHEMA_ORG . '/ListPrice',
                'price'     => number_format($regularPrice, 2, '.', ''),
                'priceCurrency' => $currencyCode,
            ],
            [
                '@type'     => 'UnitPriceSpecification',
                'priceType' => MirasvitConfig::HTTP_SCHEMA_ORG . '/SalePrice',
                'price'     => number_format($finalPrice, 2, '.', ''),
                'priceCurrency' => $currencyCode,
            ],
        ];
    }

    private function getFinalPrice(): float
    {
        $priceAmount = $this->product->getPriceInfo()
            ->getPrice(FinalPrice::PRICE_CODE)
            ->getAmount();

        if ($this->isIncludingTax()) {
            $finalPrice = $priceAmount->getValue();
        } else {
            $finalPrice = $priceAmount->getBaseAmount();
        }

        return $finalPrice;
    }

    private function getRegularPrice(): float
    {
        $priceAmount = $this->product->getPriceInfo()
            ->getPrice(RegularPrice::PRICE_CODE)
            ->getAmount();

        if ($this->isIncludingTax()) {
            return $priceAmount->getValue();
        }

        return $priceAmount->getBaseAmount();
    }

    private function getStoreId(): int
    {
        if (!isset($this->store)) {
            return Store::DEFAULT_STORE_ID;
        }

        if (!isset($this->storeId)) {
            $this->storeId = (int)$this->store->getStoreId();
        }

        return $this->storeId;
    }
}
