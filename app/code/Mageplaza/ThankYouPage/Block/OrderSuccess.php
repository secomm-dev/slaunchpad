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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Block\Order\Totals;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Api\Data\StoreInterface;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Config\Source\StaticBlock;
use Mageplaza\ThankYouPage\Model\Generate;
use Mageplaza\ThankYouPage\Model\Template;
use Mageplaza\ThankYouPage\Model\TemplateFactory;

/**
 * Class OrderSuccess
 * @package Mageplaza\ThankYouPage\Block
 */
class OrderSuccess extends Totals
{
    /**
     * @var Data
     */
    public $helperData;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var Generate
     */
    protected $generateCoupon;

    /**
     * @var PricingHelper
     */
    protected $pricingHelper;

    /**
     * @var string
     */
    protected $couponCode;

    /**
     * @var TemplateFactory
     */
    protected $templateFactory;

    /**
     * @var Template
     */
    protected $templateData;

    /**
     * OrderSuccess constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param OrderFactory $orderFactory
     * @param Data $helperData
     * @param Generate $generate
     * @param PricingHelper $pricingHelper
     * @param Context $context
     * @param Registry $registry
     * @param TemplateFactory $templateFactory
     * @param array $data
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        OrderFactory $orderFactory,
        Data $helperData,
        Generate $generate,
        PricingHelper $pricingHelper,
        Context $context,
        Registry $registry,
        TemplateFactory $templateFactory,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory   = $orderFactory;
        $this->helperData      = $helperData;
        $this->generateCoupon  = $generate;
        $this->pricingHelper   = $pricingHelper;
        $this->templateFactory = $templateFactory;

        parent::__construct($context, $registry, $data);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isEnable()
    {
        return $this->helperData->isEnabled($this->getStoreId()) &&
            $this->helperData->isOrderSuccessPageEnable($this->getStoreId()) &&
            $this->getTemplateData() !== null;
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        return $this->getStore()->getId();
    }

    /**
     * @return StoreInterface
     */
    public function getStore()
    {
        $store = $this->getOrder()->getStore();

        return $this->helperData->getStoreData($store);
    }

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->_orderFactory->create()
                ->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
            $this->_order->setData('getAllItems', $this->_order->getAllVisibleItems());
        }

        $deliveryData = $this->_order->getMpDeliveryInformation();
        if ($deliveryData && is_string($deliveryData)) {
            $deliveryData = $this->helperData->unserialize($deliveryData);

            if (isset($deliveryData['deliveryDate'])) {
                $deliveryDate = $deliveryData['deliveryDate'];
                $deliveryTime = $deliveryData['deliveryTime'] ?? null;
                $houseCode    = $deliveryData['houseSecurityCode'] ?? null;
                $comment      = $deliveryData['deliveryComment'] ?? null;

                $ddHtml = '<p>' . $deliveryDate . '</p>'
                    . '<p>' . $deliveryTime . '</p>'
                    . '<p>' . $houseCode . '</p>'
                    . '<p>' . $comment . '</p>';

                $this->_order->setData('mp_delivery_information', $ddHtml);
            }
        }

        return $this->_order;
    }

    /**
     * @param Order $orderId
     *
     * @return $this
     * @throws NoSuchEntityException
     */
    public function setOrder($orderId)
    {
        $this->_order = $this->_orderFactory->create()
            ->loadByIncrementId($orderId ?: $this->checkoutSession->getLastRealOrderId());
        if (!$this->_order->getEntityId()) {
            throw new NoSuchEntityException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }
        $this->_order->setData('getAllItems', $this->_order->getAllVisibleItems());

        return $this;
    }

    /**
     * @return Template|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getTemplateData()
    {
        if (!$this->templateData) {
            $this->templateData = $this->helperData->getTemplateData(
                Pagetype::ORDER,
                $this->getStoreId(),
                $this->getOrder()->getCustomerGroupId()
            );
        }

        return $this->templateData;
    }

    /**
     * @param Template $templateData
     *
     * @return $this
     */
    public function setTemplateData($templateData)
    {
        $this->templateData = $templateData;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSharethisKey()
    {
        return $this->helperData->getSharethisKey($this->getStoreId());
    }

    /**
     * @return mixed|string
     * @throws Exception
     */
    public function getCustomHtml()
    {
        $html = $this->helperData->processCustomHtml($this->getTemplateData(), $this->addTemplateVars());

        return $html;
    }

    /**
     * Add template vars
     * @return mixed
     * @throws Exception
     */
    public function addTemplateVars()
    {
        $templateVars = [
            'order'                    => $this->getOrder(),
            'customer'                 => $this->getCustomer(),
            'coupon'                   => $this->getCoupon(),
            'payment_html'             => $this->helperData->getPaymentHtml($this->getOrder()),
            'store'                    => $this->getStore(),
            'formattedShippingAddress' => $this->helperData->getFormattedShippingAddress($this->getOrder()),
            'formattedBillingAddress'  => $this->helperData->getFormattedBillingAddress($this->getOrder()),
            'total'                    => $this->getOrder()->formatPrice($this->getOrder()->getGrandTotal()),
            'items'                    => $this->getOrderItems(),
            'created_at_formatted'     => $this->getOrder()->getCreatedAtFormatted(2),
            'sharethis_key'            => $this->helperData->getSharethisKey($this->getStoreId()),
            'static_block_1'           => $this->getStaticBlock1(),
            'static_block_2'           => $this->getStaticBlock2()
        ];

        $templateVars = array_merge($templateVars, $this->helperData->getTemplateVars());

        return $templateVars;
    }

    /**
     * @return \Magento\Customer\Model\Customer
     * @throws LocalizedException
     */
    public function getCustomer()
    {
        $customer = $this->customerSession->getCustomer();
        $customer->setData('name', $customer->getName());

        return $customer;
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCoupon()
    {
        if ($this->getTemplateData()->getEnableCoupon() && $this->getTemplateData()->getRuleId() != 0) {
            $config = $this->getTemplateData()->getData();
            if ($this->getCouponCode($config)) {
                return $this->helperData->getCouponInfo($config, $this->getCouponCode($config));
            }
        }

        return [];
    }

    /**
     * @param array $config
     *
     * @return array|string|string[]|null
     * @throws LocalizedException
     */
    public function getCouponCode($config)
    {
        if (!$this->couponCode) {
            $this->couponCode = $this->generateCoupon->generateCoupon($config);
        }

        return $this->couponCode;
    }

    /**
     * Retrieve all item of order
     *
     * @return string
     */
    public function getOrderItems()
    {
        $items = '';
        foreach ($this->getOrder()->getAllVisibleItems() as $_item) {
            /* @var $_item Item */
            $items .=
                $_item->getProduct()->getName() . '  x ' . $_item->getQty() . ' ' .
                $this->pricingHelper->currency(
                    $_item->getProduct()->getFinalPrice($_item->getQty()),
                    true,
                    false
                ) . "\n";
        }

        return nl2br($items);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getStaticBlock1()
    {
        return $this->getStaticBlock('static_block_1');
    }

    /**
     * @param string $blockName
     *
     * @return string
     * @throws LocalizedException
     */
    public function getStaticBlock($blockName)
    {
        $templateData = $this->getTemplateData()->getData();
        $blockId      = $templateData[$blockName];
        $block        = '';
        if ($blockId !== StaticBlock::DISABLE) {
            $block = $this->getLayout()
                ->createBlock('Magento\Cms\Block\Block')
                ->setBlockId($blockId)
                ->toHtml();
        }

        return $block;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getStaticBlock2()
    {
        return $this->getStaticBlock('static_block_2');
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCustomCss()
    {
        if ($this->helperData->checkHyvaTheme()) {
            return $this->getTemplateData()->getHyvaCustomCss();
        }

        return $this->getTemplateData()->getCustomCss();
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
     * @param string $config
     *
     * @return string
     * @throws Exception
     */
    public function getPageInfo($config)
    {
        $configValue          = $this->getTemplateData()->getData();
        $processedInformation = $this->helperData->processLiquidTemplate(
            $configValue[$config],
            $this->addTemplateVars()
        );

        return $processedInformation;
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
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getContinueUrl()
    {
        $continueUrl = '';
        $continueBtn = $this->getTemplateData()->getContinueButton();
        if ($continueBtn === '1') {
            $continueUrl = $this->_storeManager->getStore($this->getStoreId())->getBaseUrl();
        }

        return $continueUrl;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getOrderDetail()
    {
        $detail = '';
        if ($this->checkEnableBlock('order_detail')) {
            $detail = $this->helperData->getOrderDetailHtml($this->getStyle(), $this->addTemplateVars());
        }

        return $detail;
    }

    /**
     * @param $name
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function checkEnableBlock($name)
    {
        $block = $this->getTemplateData()->getBlock();

        return strpos($block, $name) !== false;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStyle()
    {
        return $this->getTemplateData()->getStyle();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getCouponHtml()
    {
        $couponHtml = '';
        if ($this->getTemplateData()->getEnableCoupon()) {
            $couponHtml = $this->getPageInfo('coupon_label');
        }

        return $couponHtml;
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function checkCustom()
    {
        return $this->getTemplateData()->getCustomStyle();
    }

    /**
     * Retrieve form action url and set "secure" param to avoid confirm
     * message when we submit form from secure page to unsecure
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('newsletter/subscriber/new', ['_secure' => true]);
    }

    /**
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->_order->getCustomerEmail();
    }
}
