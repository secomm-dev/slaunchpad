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

namespace Mageplaza\ThankYouPage\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\ResourceModel\Iterator;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Rule\Model\AbstractModel;
use Magento\Sales\Model\OrderFactory;
use Magento\SalesRule\Api\Data\ConditionInterface;
use Magento\SalesRule\Model\Rule\Condition\CombineFactory;
use Magento\SalesRule\Model\Rule\Condition\Product\Combine;
use Magento\SalesRule\Model\Rule\Condition\Product\CombineFactory as ProductCombineFactory;
use Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface;
use Mageplaza\ThankYouPage\Helper\Data;

/**
 * Class Template
 * @package Mageplaza\ThankYouPage\Model
 */
class Template extends AbstractModel implements ThankYouPageInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_thankyoupage_templates';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_thankyoupage_templates';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_thankyoupage_templates';

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var CombineFactory
     */
    protected $_salesCombineFactory;

    /**
     * @var ProductCombineFactory
     */
    protected $_condProdCombineF;

    /**
     * @var Iterator
     */
    protected $resourceIterator;

    /**
     * Store matched product Ids in condition tab
     *
     * @var array
     */
    protected $productConditionsIds;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var QuoteFactory
     */
    protected $quote;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param Session $checkoutSession
     * @param Data $helperData
     * @param CombineFactory $salesCombineFactory
     * @param ProductCombineFactory $condProdCombineF
     * @param Iterator $resourceIterator
     * @param OrderFactory $orderFactory
     * @param QuoteFactory $quote
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        Session $checkoutSession,
        Data $helperData,
        CombineFactory $salesCombineFactory,
        ProductCombineFactory $condProdCombineF,
        Iterator $resourceIterator,
        OrderFactory $orderFactory,
        QuoteFactory $quote,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_salesCombineFactory = $salesCombineFactory;
        $this->_condProdCombineF    = $condProdCombineF;
        $this->checkoutSession      = $checkoutSession;
        $this->helperData           = $helperData;
        $this->resourceIterator     = $resourceIterator;
        $this->_orderFactory        = $orderFactory;
        $this->quote                = $quote;

        parent::__construct($context, $registry, $formFactory, $localeDate, $resource, $resourceCollection, $data);
    }

    /**
     * @return void
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('Mageplaza\ThankYouPage\Model\ResourceModel\Template');
    }

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * get order default values
     *
     * @return array
     */
    public function getOrderDefaultValues()
    {
        return [
            'title'                => __('Thank you for your purchase!'),
            'sub_title'            => __('Your order has been placed and will be processed as soon as possible.'),
            'description'          => '<div class="info"><p>' . __('Your order number:') . '<b>#<a href="{{ store.base_url }}sales/order/view/order_id/{{ order.entity_id }}">{{ order.increment_id }}</a></b></p></div>',
            'continue_button'      => '1',
            'block'                => 'order_detail,social_sharing,account,subscribe',
            'static_block_1'       => '0',
            'static_block_2'       => '0',
            'faq_limit'            => '5',
            'product_slider_title' => __('You may also like'),
            'product_slider_limit' => '10',
            'coupon_pattern'       => '[12AN]',
            'coupon_label'         => '<div class="order-coupons">' . __(
                    'Use this coupon code: %1 to get %2 on your next order.',
                    '<strong class="mp-coupon">{{ coupon.code }}</strong>',
                    '<strong class="mp-coupon">{{ coupon.discount_amount }}</strong>'
                ) . '</div>'
        ];
    }

    /**
     * get newsletter default values
     *
     * @return array
     */
    public function getNewsletterDefaultValues()
    {
        return [
            'title'           => __('Thank you for signing up!'),
            'sub_title'       => __('Welcome you to our exclusive newsletters with every updates'),
            'description'     => __('You would be the first to reach our latest arrivals, news, events, various special promotions, giveaways and more.')
                . __('Stay tuned and keep updated. All the best!'),
            'static_block_1'  => '0',
            'faq_limit'       => '5',
            'coupon_pattern'  => '[12AN]',
            'coupon_label'    => '<div class="subscribe-coupons">' . __(
                    'Enter coupon code: %1 when you make your next purchase enjoy %2 off',
                    '<strong class="mp-coupon">{{ coupon.code }}</strong>',
                    '<strong class="mp-coupon">{{ coupon.discount_amount }}</strong>'
                ) . '</div>',
            'custom_css'      => '.mpthankyoupage.newsletter {
        text-align: center;
        margin-bottom: 20px
    }
/* Design for style 1   */
    .mpthankyoupage .mp-container.style1 i.fa.fa-check-circle {
        font-size: 60px;
        color: #ffc107;
    }
    .mpthankyoupage .mp-container.style1 .title {
        font-size: 40px;
        font-weight: bold;
        margin-top: 25px;
        margin-bottom: 20px;
    }
    .mpthankyoupage .mp-container.style1 .subtitle {
        font-size: large;
        font-weight: 600;
        color: #eb5202;
    }
    .mpthankyoupage .mp-container.style1 .action.primary {
        background: #ffc107;
        border: 1px solid #ffc107;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    .mpthankyoupage .mp-container.style1 .mp-coupon {
        color: #eb5202;
    }
    /* End */

/* Design for style 2*/
    .mpthankyoupage.newsletter .mp-container.style2 {
        margin: 0 auto;
        background-color: #f86c6b;
        padding: 100px 50px 20px;
    }
    .mpthankyoupage.newsletter .mp-container.style2 .mp-content {
        background-color: #fff;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .title {
        font-family: Cambria, Georgia, serif;
        font-size: 50px;
        font-variant: normal;
        line-height: 70px;
        font-style: normal;
        margin-bottom: 10px;
        padding-top: 15px;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .subtitle {
        font-size: 16px;
        margin-bottom: 10px;
        margin-top: 10px;
        color: #f86c6b;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .additional {
        margin-top: 20px;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .order-coupons {
        padding: 1px 20px 5px;
        font-family: Cambria, Georgia, serif;
        margin: 10px 35px;
    }

    .mpthankyoupage .mp-container.style2 .mp-content strong.mp-coupon {
        color: #f86c6b;
        font-size: 18px;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .action.primary {
        background: #f86c6b;
        border: 1px solid #eb5202;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    /* End */

/* Design for style 3*/
    .mp-container.style3 {
        background-image: url(https://i.imgur.com/kSOYqE3.jpg);
        height: 985px;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        position: relative;
    }
    .mpthankyoupage .mp-container.style3 .mp-content .action.primary {
        background: #1979c3;
        border: 1px solid #1979c3;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    .mpthankyoupage .mp-container.style3 .mp-content .title {
        font-family: Arial Black, Arial Bold, Gadget, sans-serif;
        font-size: 40px;
        font-variant: normal;
        font-weight: bold;
        line-height: 70px;
        font-style: normal;
        margin-bottom: 5px;
        color: #1979c3;
    }
    .mp-container.style3 .fa.fa-check {
        font-size: 90px;
        color: #1979c3;
    }
    .mpthankyoupage .mp-container.style3 .mp-content .subtitle, .mpthankyoupage .mp-container.style3 .mp-content .description {
        font-size: 1.7rem;
    }
    .mpthankyoupage .mp-container.style3 .mp-content strong.mp-coupon {
        color: #1979c3;
        font-size: 20px;
    }
    /* End*/',
            'hyva_custom_css' => '.mpthankyoupage.newsletter {
        text-align: center;
        margin-bottom: 20px
    }
/* Design for style 1   */
    .mpthankyoupage .mp-container.style1 .check-icon {
        color: #ffc107;
    }
    .mpthankyoupage .mp-container.style1 .title {
        font-size: 40px;
        font-weight: bold;
        margin-top: 25px;
        margin-bottom: 20px;
    }
    .mpthankyoupage .mp-container.style1 .subtitle {
        font-size: large;
        font-weight: 600;
        color: #eb5202;
    }
    .mpthankyoupage .mp-container.style1 .action.primary {
        background: #ffc107;
        border: 1px solid #ffc107;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    .mpthankyoupage .mp-container.style1 .mp-coupon {
        color: #eb5202;
    }
    /* End */

/* Design for style 2*/
    .mpthankyoupage.newsletter .mp-container.style2 {
        margin: 0 auto;
        background-color: #f86c6b;
        padding: 100px 50px 20px;
    }
    .mpthankyoupage.newsletter .mp-container.style2 .mp-content {
        background-color: #fff;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .title {
        font-family: Cambria, Georgia, serif;
        font-size: 50px;
        font-variant: normal;
        line-height: 70px;
        font-style: normal;
        margin-bottom: 10px;
        padding-top: 15px;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .subtitle {
        font-size: 16px;
        margin-bottom: 10px;
        margin-top: 10px;
        color: #f86c6b;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .additional {
        margin-top: 20px;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .order-coupons {
        padding: 1px 20px 5px;
        font-family: Cambria, Georgia, serif;
        margin: 10px 35px;
    }

    .mpthankyoupage .mp-container.style2 .mp-content strong.mp-coupon {
        color: #f86c6b;
        font-size: 18px;
    }
    .mpthankyoupage .mp-container.style2 .mp-content .action.primary {
        background: #f86c6b;
        border: 1px solid #eb5202;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    /* End */

/* Design for style 3*/
    .mp-container.style3 {
        background-image: url(https://i.imgur.com/kSOYqE3.jpg);
        height: 985px;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        position: relative;
    }
    .mpthankyoupage .mp-container.style3 .mp-content .action.primary {
        background: #1979c3;
        border: 1px solid #1979c3;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    .mpthankyoupage .mp-container.style3 .mp-content .title {
        font-family: Arial Black, Arial Bold, Gadget, sans-serif;
        font-size: 40px;
        font-variant: normal;
        font-weight: bold;
        line-height: 70px;
        font-style: normal;
        margin-bottom: 5px;
        color: #1979c3;
    }
    .mp-container.style3 .check-icon {
        color: #1979c3;
    }
    .mpthankyoupage .mp-container.style3 .mp-content .subtitle, .mpthankyoupage .mp-container.style3 .mp-content .description {
        font-size: 1.2rem;
    }
    .mpthankyoupage .mp-container.style3 .mp-content strong.mp-coupon {
        color: #1979c3;
        font-size: 20px;
    }
    /* End*/'
        ];
    }

    /**
     * Get condition combine model instance
     *
     * @return \Magento\CatalogRule\Model\Rule\Condition\Combine|\Magento\SalesRule\Model\Rule\Condition\Combine
     */
    public function getConditionsInstance()
    {
        return $this->_salesCombineFactory->create();
    }

    /**
     * Get condition product combine model instance
     *
     * @return Combine
     */
    public function getActionsInstance()
    {
        return $this->_condProdCombineF->create();
    }

    /**
     * @param string $formName
     *
     * @return string
     */
    public function getConditionsFieldSetId($formName = '')
    {
        return $formName . 'mpthankyoupage_conditions_fieldset_' . $this->getId();
    }

    /**
     * @param null $orderId
     *
     * @return mixed|string
     */
    public function getApplyTemplate($orderId = null)
    {
        $templateId = '';
        $order      = $this->_orderFactory->create()->loadByIncrementId($orderId ?: $this->checkoutSession->getLastRealOrderId());
        $quote      = $this->quote->create()->load($order->getQuoteId());

        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $address->setData('total_qty', $quote->getItemsQty());

        if ($this->getConditions()->validate($address)) {
            $templateId = $this->getId();
        }

        return $templateId;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateId()
    {
        return $this->getData(self::TEMPLATE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setTemplateId($value)
    {
        return $this->setData(self::TEMPLATE_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getData(self::NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setName($value)
    {
        return $this->setData(self::NAME, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getPageType()
    {
        return $this->getData(self::PAGE_TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function setPageType($value)
    {
        return $this->setData(self::PAGE_TYPE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus($value)
    {
        return $this->setData(self::STATUS, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreIds()
    {
        return $this->getData(self::STORE_IDS);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreIds($value)
    {
        return $this->setData(self::STORE_IDS, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerGroupIds()
    {
        return $this->getData(self::CUSTOMER_GROUP_IDS);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerGroupIds($value)
    {
        return $this->setData(self::CUSTOMER_GROUP_IDS, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return $this->getData(self::PRIORITY);
    }

    /**
     * {@inheritdoc}
     */
    public function setPriority($value)
    {
        return $this->setData(self::PRIORITY, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCondition()
    {
        return $this->getData(self::CONDITION);
    }

    /**
     * {@inheritdoc}
     */
    public function setCondition(?ConditionInterface $condition = null)
    {
        return $this->setData(self::CONDITION, $condition);
    }

    /**
     * {@inheritdoc}
     */
    public function getStyle()
    {
        return $this->getData(self::STYLE);
    }

    /**
     * {@inheritdoc}
     */
    public function setStyle($value)
    {
        return $this->setData(self::STYLE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomStyle()
    {
        return $this->getData(self::CUSTOM_STYLE);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomStyle($value)
    {
        return $this->setData(self::CUSTOM_STYLE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle()
    {
        return $this->getData(self::TITLE);
    }

    /**
     * {@inheritdoc}
     */
    public function setTitle($value)
    {
        return $this->setData(self::TITLE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubTitle()
    {
        return $this->getData(self::SUB_TITLE);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubTitle($value)
    {
        return $this->setData(self::SUB_TITLE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * {@inheritdoc}
     */
    public function setDescription($value)
    {
        return $this->setData(self::DESCRIPTION, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getContinueButton()
    {
        return $this->getData(self::CONTINUE_BUTTON);
    }

    /**
     * {@inheritdoc}
     */
    public function setContinueButton($value)
    {
        return $this->setData(self::CONTINUE_BUTTON, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlock()
    {
        return $this->getData(self::BLOCK);
    }

    /**
     * {@inheritdoc}
     */
    public function setBlock($value)
    {
        return $this->setData(self::BLOCK, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomHtml()
    {
        return $this->getData(self::CUSTOM_HTML);
    }

    /**
     * {@inheritdoc}
     */
    public function getHyvaCustomHtml()
    {
        return $this->getData(self::HYVA_CUSTOM_HTML);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomHtml($value)
    {
        return $this->setData(self::CUSTOM_HTML, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomCss()
    {
        return $this->getData(self::CUSTOM_CSS);
    }

    /**
     * {@inheritdoc}
     */
    public function getHyvaCustomCss()
    {
        return $this->getData(self::HYVA_CUSTOM_CSS);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomCss($value)
    {
        return $this->setData(self::CUSTOM_CSS, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getHtml()
    {
        return $this->getData(self::HTML);
    }

    /**
     * {@inheritdoc}
     */
    public function setHtml($value)
    {
        return $this->setData(self::HTML, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticBlock1()
    {
        return $this->getData(self::STATIC_BLOCK_1);
    }

    /**
     * {@inheritdoc}
     */
    public function setStaticBlock1($value)
    {
        return $this->setData(self::STATIC_BLOCK_1, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticBlock2()
    {
        return $this->getData(self::STATIC_BLOCK_2);
    }

    /**
     * {@inheritdoc}
     */
    public function setStaticBlock2($value)
    {
        return $this->setData(self::STATIC_BLOCK_2, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getSocialShare()
    {
        return $this->getData(self::SOCIAL_SHARE);
    }

    /**
     * {@inheritdoc}
     */
    public function setSocialShare($value)
    {
        return $this->setData(self::SOCIAL_SHARE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnableCoupon()
    {
        return $this->getData(self::ENABLE_COUPON);
    }

    /**
     * {@inheritdoc}
     */
    public function setEnableCoupon($value)
    {
        return $this->setData(self::ENABLE_COUPON, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleId()
    {
        return $this->getData(self::RULE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setRuleId($value)
    {
        return $this->setData(self::RULE_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCouponPattern()
    {
        return $this->getData(self::COUPON_PATTERN);
    }

    /**
     * {@inheritdoc}
     */
    public function setCouponPattern($value)
    {
        return $this->setData(self::COUPON_PATTERN, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCouponLabel()
    {
        return $this->getData(self::COUPON_LABEL);
    }

    /**
     * {@inheritdoc}
     */
    public function setCouponLabel($value)
    {
        return $this->setData(self::COUPON_LABEL, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnableFaq()
    {
        return $this->getData(self::ENABLE_FAQ);
    }

    /**
     * {@inheritdoc}
     */
    public function setEnableFaq($value)
    {
        return $this->setData(self::ENABLE_FAQ, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getFaqTitle()
    {
        return $this->getData(self::FAQ_TITLE);
    }

    /**
     * {@inheritdoc}
     */
    public function setFaqTitle($value)
    {
        return $this->setData(self::FAQ_TITLE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getFaqCategory()
    {
        return $this->getData(self::FAQ_CATEGORY);
    }

    /**
     * {@inheritdoc}
     */
    public function setFaqCategory($value)
    {
        return $this->setData(self::FAQ_CATEGORY, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getFaqLimit()
    {
        return $this->getData(self::FAQ_LIMIT);
    }

    /**
     * {@inheritdoc}
     */
    public function setFaqLimit($value)
    {
        return $this->setData(self::FAQ_LIMIT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnableProductSlider()
    {
        return $this->getData(self::ENABLE_PRODUCT_SLIDER);
    }

    /**
     * {@inheritdoc}
     */
    public function setEnableProductSlider($value)
    {
        return $this->setData(self::ENABLE_PRODUCT_SLIDER, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductSliderId()
    {
        return $this->getData(self::PRODUCT_SLIDER_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setProductSliderId($value)
    {
        return $this->setData(self::PRODUCT_SLIDER_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductSliderTitle()
    {
        return $this->getData(self::PRODUCT_SLIDER_TITLE);
    }

    /**
     * {@inheritdoc}
     */
    public function setProductSliderTitle($value)
    {
        return $this->setData(self::PRODUCT_SLIDER_TITLE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductSliderLimit()
    {
        return $this->getData(self::PRODUCT_SLIDER_LIMIT);
    }

    /**
     * {@inheritdoc}
     */
    public function setProductSliderLimit($value)
    {
        return $this->setData(self::PRODUCT_SLIDER_LIMIT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductSliderAdditional()
    {
        return $this->getData(self::PRODUCT_SLIDER_ADDITIONAL);
    }

    /**
     * {@inheritdoc}
     */
    public function setProductSliderAdditional($value)
    {
        return $this->setData(self::PRODUCT_SLIDER_ADDITIONAL, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt($value)
    {
        return $this->setData(self::CREATED_AT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setUpdatedAt($value)
    {
        return $this->setData(self::UPDATED_AT, $value);
    }
}
