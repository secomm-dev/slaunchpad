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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Block;

use Exception;
use Magento\Cms\Block\Block;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Store\Api\Data\StoreInterface;
use Mageplaza\Productslider\Block\Widget\Slider;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Config\Source\ProductSlider;
use Mageplaza\ThankYouPage\Model\Config\Source\StaticBlock;

/**
 * Class Subscribe
 * @package Mageplaza\ThankYouPage\Block
 */
class Subscribe extends Template
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Currency
     */
    protected $_currency;

    /**
     * @var \Mageplaza\ThankYouPage\Model\Template
     */
    protected $templateData;

    /**
     * @var string
     */
    protected $storeId;

    /**
     * Subscribe constructor.
     *
     * @param Template\Context $context
     * @param Data $helperData
     * @param CustomerSession $customerSession
     * @param Currency $currency
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helperData,
        CustomerSession $customerSession,
        Currency $currency,
        array $data = []
    ) {
        $this->helperData      = $helperData;
        $this->customerSession = $customerSession;
        $this->_currency       = $currency;

        parent::__construct($context, $data);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function isEnable()
    {
        return $this->helperData->isEnabled($this->getStoreId()) &&
            $this->helperData->isNewsletterSuccessPageEnable($this->getStoreId()) &&
            $this->getTemplateData() !== null;
    }

    /**
     * @param \Mageplaza\ThankYouPage\Model\Template $templateData
     *
     * @return $this
     */
    public function setTemplateData($templateData)
    {
        $this->templateData = $templateData;

        return $this;
    }

    /**
     * @return \Mageplaza\ThankYouPage\Model\Template|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getTemplateData()
    {
        if (!$this->templateData) {
            $this->templateData = $this->helperData->getTemplateData(Pagetype::NEWSLETTER);
        }

        return $this->templateData;
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getStyle()
    {
        $templateData = $this->getTemplateData();

        return !$templateData ?: $templateData->getStyle();
    }

    /**
     * @return Customer
     * @throws LocalizedException
     */
    public function getCustomer()
    {
        $customer = $this->customerSession->getCustomer();
        $customer->setData('name', $customer->getName());

        return $customer;
    }

    /**
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    public function getStore()
    {
        $store = $this->_storeManager->getStore();

        return $this->helperData->getStoreData($store);
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        if (!$this->storeId) {
            $this->storeId = $this->getStore()->getId();
        }

        return $this->storeId;
    }

    /**
     * @param int $storeId
     *
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;

        return $this;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getPageTitle()
    {
        return $this->getPageInfo('title');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getPageSubTitle()
    {
        return $this->getPageInfo('sub_title');
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getPageDescription()
    {
        return $this->getPageInfo('description');
    }

    /**
     * @param string $config
     *
     * @return string|null
     * @throws Exception
     */
    public function getPageInfo($config)
    {
        $templateData = $this->getTemplateData();
        if (!$templateData) {
            return null;
        }

        $processedInformation = $this->helperData->processLiquidTemplate(
            $templateData->getData()[$config],
            $this->addTemplateVars()
        );

        return $processedInformation;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getNewsletterCss()
    {
        $templateData = $this->getTemplateData();

        if ($templateData) {
            if ($this->helperData->checkHyvaTheme()) {
                return $templateData->getHyvaCustomCss();
            }

            return $templateData->getCustomCss();
        }
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getSharethisKey()
    {
        return $this->helperData->getSharethisKey($this->getStoreId());
    }


    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function addTemplateVars()
    {
        $templateVars = [
            'customer' => $this->getCustomer(),
            'coupon'   => $this->getCoupon(),
            'store'    => $this->getStore(),
        ];

        return $templateVars;
    }

    /**
     * @return int|mixed
     */
    public function checkEnableSocialShare()
    {
        return $this->templateData->getSocialShare();
    }

    /**
     * @return bool|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function checkEnableCoupon()
    {
        $templateData = $this->getTemplateData();

        return !$templateData ?: $templateData->getEnableCoupon();
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getCouponHtml()
    {
        $couponHtml = '';
        if ($this->checkEnableCoupon()) {
            $couponHtml = $this->getPageInfo('coupon_label');
        }

        return $couponHtml;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCoupon()
    {
        if (!$this->checkEnableCoupon()) {
            return [];
        }
        $template = $this->getTemplateData();
        $config   = !$template ?: $template->getData();
        $this->_session->start();

        return $this->helperData->getCouponInfo($config, $this->_session->getData('coupon_code'));
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getProductSlider()
    {
        if (!$this->helperData->isNewsletterSuccessPageEnable()) {
            return '';
        }
        $templateData = $this->getTemplateData();
        $type         = !$templateData ?: $templateData->getProductSliderId();
        $slider       = '';
        if ($type !== ProductSlider::DISABLE &&
            $this->helperData->getConfigValue('productslider/general/enabled', $this->getStoreId())
        ) {
            $slider = $this->getLayout()
                ->createBlock(Slider::class)
                ->setProductType($type)->toHtml();
        }

        return $slider;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getStaticBlock()
    {
        if (!$this->helperData->isNewsletterSuccessPageEnable()) {
            return '';
        }

        $templateData = $this->getTemplateData();
        $blockId      = !$templateData ?: $templateData->getStaticBlock1();
        $block        = '';
        if ($blockId !== StaticBlock::DISABLE) {
            $block = $this->getLayout()
                ->createBlock(Block::class)
                ->setBlockId($blockId)
                ->toHtml();
        }
        $block = $this->helperData->processLiquidTemplate($block, $this->addTemplateVars());

        return $block;
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getContinueUrl()
    {
        return $this->_storeManager->getStore($this->getStoreId())->getBaseUrl();
    }
}
