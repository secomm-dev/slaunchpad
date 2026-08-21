<?php

namespace Mageplaza\ExtraFee\Block\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\View as ProductView;
use Magento\Catalog\Helper\Product;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Json\EncoderInterface as JsonEncoderInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url\EncoderInterface;
use Mageplaza\ExtraFee\Helper\Data;

class View extends ProductView
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * View constructor.
     *
     * @param Context $context
     * @param EncoderInterface $urlEncoder
     * @param JsonEncoderInterface $jsonEncoder
     * @param StringUtils $string
     * @param Product $productHelper
     * @param ConfigInterface $productTypeConfig
     * @param FormatInterface $localeFormat
     * @param Session $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        EncoderInterface $urlEncoder,
        JsonEncoderInterface $jsonEncoder,
        StringUtils $string,
        Product $productHelper,
        ConfigInterface $productTypeConfig,
        FormatInterface $localeFormat,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        Data $helperData,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );

        $this->helperData = $helperData;
    }

    /**
     * Check configuration display extra fee on product page
     *
     * @return array|mixed
     */
    public function isDisplayExtraFee()
    {
        return $this->helperData->isEnabled() && $this->helperData->getConfigGeneral('display_on_product_page');
    }

    /**
     * Get extra fee block title
     *
     * @return array|mixed
     */
    public function getExtraFeeTitle()
    {
        return $this->helperData->getConfigGeneral('display_product_page_title');
    }

    /**
     * Get extra block position
     *
     * @return array|mixed
     */
    public function getExtraFeePosition()
    {
        return $this->helperData->getConfigGeneral('display_product_page_position');
    }
}
