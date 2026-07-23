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

namespace Mageplaza\ThankYouPage\Api\Data;

use Magento\SalesRule\Api\Data\ConditionInterface;

/**
 * Interface ThankYouPageInterface
 * @package Mageplaza\ThankYouPage\Api\Data
 */
interface ThankYouPageInterface
{
    const TEMPLATE_ID               = 'template_id';
    const NAME                      = 'name';
    const PAGE_TYPE                 = 'page_type';
    const STATUS                    = 'status';
    const STORE_IDS                 = 'store_ids';
    const CUSTOMER_GROUP_IDS        = 'customer_group_ids';
    const PRIORITY                  = 'priority';
    const CONDITION                 = 'condition';
    const STYLE                     = 'style';
    const CUSTOM_STYLE              = 'custom_style';
    const TITLE                     = 'title';
    const SUB_TITLE                 = 'sub_title';
    const DESCRIPTION               = 'description';
    const CONTINUE_BUTTON           = 'continue_button';
    const BLOCK                     = 'block';
    const CUSTOM_HTML               = 'custom_html';
    const CUSTOM_CSS                = 'custom_css';
    const HYVA_CUSTOM_CSS           = 'hyva_custom_css';
    const HYVA_CUSTOM_HTML          = 'hyva_custom_html';
    const STATIC_BLOCK_1            = 'static_block_1';
    const STATIC_BLOCK_2            = 'static_block_2';
    const SOCIAL_SHARE              = 'social_share';
    const ENABLE_COUPON             = 'enable_coupon';
    const RULE_ID                   = 'rule_id';
    const COUPON_PATTERN            = 'coupon_pattern';
    const COUPON_LABEL              = 'coupon_label';
    const ENABLE_FAQ                = 'enable_faq';
    const FAQ_TITLE                 = 'faq_title';
    const FAQ_CATEGORY              = 'faq_category';
    const FAQ_LIMIT                 = 'faq_limit';
    const ENABLE_PRODUCT_SLIDER     = 'enable_product_slider';
    const PRODUCT_SLIDER_ID         = 'product_slider_id';
    const PRODUCT_SLIDER_TITLE      = 'product_slider_title';
    const PRODUCT_SLIDER_LIMIT      = 'product_slider_limit';
    const PRODUCT_SLIDER_ADDITIONAL = 'product_slider_additional';
    const CREATED_AT                = 'created_at';
    const UPDATED_AT                = 'updated_at';
    const HTML                      = 'html';

    /**
     * @return int
     */
    public function getTemplateId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setTemplateId($value);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setName($value);

    /**
     * @return string
     */
    public function getPageType();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setPageType($value);

    /**
     * @return int
     */
    public function getStatus();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStatus($value);

    /**
     * @return string
     */
    public function getStoreIds();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setStoreIds($value);

    /**
     * @return string
     */
    public function getCustomerGroupIds();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCustomerGroupIds($value);

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setPriority($value);

    /**
     * Get condition for the rule
     *
     * @return \Magento\SalesRule\Api\Data\ConditionInterface|null
     */
    public function getCondition();

    /**
     * Set condition for the rule
     *
     * @param \Magento\SalesRule\Api\Data\ConditionInterface|null $condition
     *
     * @return $this
     */
    public function setCondition(?ConditionInterface $condition = null);

    /**
     * @return string
     */
    public function getStyle();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setStyle($value);

    /**
     * @return int
     */
    public function getCustomStyle();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setCustomStyle($value);

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setTitle($value);

    /**
     * @return string
     */
    public function getSubTitle();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setSubTitle($value);

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setDescription($value);

    /**
     * @return int
     */
    public function getContinueButton();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setContinueButton($value);

    /**
     * @return string
     */
    public function getBlock();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setBlock($value);

    /**
     * @return string
     */
    public function getCustomHtml();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCustomHtml($value);

    /**
     * @return string
     */
    public function getCustomCss();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCustomCss($value);

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setHtml($value);

    /**
     * @return string
     */
    public function getHtml();

    /**
     * @return int
     */
    public function getStaticBlock1();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStaticBlock1($value);

    /**
     * @return int
     */
    public function getStaticBlock2();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStaticBlock2($value);

    /**
     * @return int
     */
    public function getSocialShare();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setSocialShare($value);

    /**
     * @return int
     */
    public function getEnableCoupon();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setEnableCoupon($value);

    /**
     * @return int
     */
    public function getRuleId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setRuleId($value);

    /**
     * @return string
     */
    public function getCouponPattern();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCouponPattern($value);

    /**
     * @return string
     */
    public function getCouponLabel();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCouponLabel($value);

    /**
     * @return int
     */
    public function getEnableFaq();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setEnableFaq($value);

    /**
     * @return string
     */
    public function getFaqTitle();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setFaqTitle($value);

    /**
     * @return int
     */
    public function getFaqCategory();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setFaqCategory($value);

    /**
     * @return int
     */
    public function getFaqLimit();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setFaqLimit($value);

    /**
     * @return int
     */
    public function getEnableProductSlider();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setEnableProductSlider($value);

    /**
     * @return string
     */
    public function getProductSliderId();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setProductSliderId($value);

    /**
     * @return string
     */
    public function getProductSliderTitle();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setProductSliderTitle($value);

    /**
     * @return int
     */
    public function getProductSliderLimit();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setProductSliderLimit($value);

    /**
     * @return string
     */
    public function getProductSliderAdditional();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setProductSliderAdditional($value);

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCreatedAt($value);

    /**
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setUpdatedAt($value);
}
