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

namespace Mageplaza\ThankYouPage\Block\Adminhtml\Template\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Additional;
use Mageplaza\ThankYouPage\Model\Config\Source\CartRules;
use Mageplaza\ThankYouPage\Model\Config\Source\CustomStyle;
use Mageplaza\ThankYouPage\Model\Config\Source\EnableFAQ;
use Mageplaza\ThankYouPage\Model\Config\Source\Faqs;
use Mageplaza\ThankYouPage\Model\Config\Source\NewsletterPageStyle;
use Mageplaza\ThankYouPage\Model\Config\Source\OrderBlock;
use Mageplaza\ThankYouPage\Model\Config\Source\OrderPageStyle;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Config\Source\ProductSlider;
use Mageplaza\ThankYouPage\Model\Config\Source\StaticBlock;
use Mageplaza\ThankYouPage\Model\Template\TemplateVars;

/**
 * Class Display
 * @package Mageplaza\ThankYouPage\Block\Adminhtml\Template\Edit\Tab
 */
class Display extends Generic implements TabInterface
{
    /**
     * @var string
     */
    protected $_template = 'Mageplaza_ThankYouPage::system/config/frontend_model.phtml';

    /**
     * @var OrderPageStyle
     */
    protected $orderStyle;

    /**
     * @var CustomStyle
     */
    protected $customStyle;

    /**
     * @var NewsletterPageStyle
     */
    protected $newsletterStyle;

    /**
     * @var OrderBlock
     */
    protected $orderBlock;

    /**
     * @var StaticBlock
     */
    protected $staticBlock;

    /**
     * @var ProductSlider
     */
    protected $productSlider;

    /**
     * @var Yesno
     */
    protected $statusOptions;

    /**
     * @var CartRules
     */
    protected $cartRules;

    /**
     * @var Additional
     */
    protected $sliderAdditional;

    /**
     * @var EnableFAQ
     */
    protected $enableFaq;

    /**
     * @var Faqs
     */
    protected $faqCategory;

    /**
     * @var TemplateVars
     */
    protected $templateVar;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Display constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param OrderPageStyle $orderPageStyle
     * @param CustomStyle $customStyle
     * @param NewsletterPageStyle $newsletterPageStyle
     * @param OrderBlock $orderBlock
     * @param StaticBlock $staticBlock
     * @param ProductSlider $productSlider
     * @param Yesno $enabledisable
     * @param CartRules $cartRules
     * @param Additional $sliderAdditional
     * @param EnableFAQ $enableFAQ
     * @param Faqs $faqCategory
     * @param TemplateVars $templateVars
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        OrderPageStyle $orderPageStyle,
        CustomStyle $customStyle,
        NewsletterPageStyle $newsletterPageStyle,
        OrderBlock $orderBlock,
        StaticBlock $staticBlock,
        ProductSlider $productSlider,
        Yesno $enabledisable,
        CartRules $cartRules,
        Additional $sliderAdditional,
        EnableFAQ $enableFAQ,
        Faqs $faqCategory,
        TemplateVars $templateVars,
        Data $helperData,
        array $data = []
    ) {
        $this->orderStyle       = $orderPageStyle;
        $this->orderBlock       = $orderBlock;
        $this->newsletterStyle  = $newsletterPageStyle;
        $this->customStyle      = $customStyle;
        $this->staticBlock      = $staticBlock;
        $this->productSlider    = $productSlider;
        $this->statusOptions    = $enabledisable;
        $this->cartRules        = $cartRules;
        $this->sliderAdditional = $sliderAdditional;
        $this->enableFaq        = $enableFAQ;
        $this->faqCategory      = $faqCategory;
        $this->templateVar      = $templateVars;
        $this->helperData       = $helperData;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @return Generic
     * @throws LocalizedException
     */
    public function _prepareForm()
    {
        $model        = $this->_coreRegistry->registry('mpthankyoupage_template');
        $pagetype     = $this->_coreRegistry->registry('mpthankyoupage_pagetype');
        $hasHyvaTheme = $this->helperData->hasHyvaTheme();

        /** @var Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('mpthankyoupage_');
        $form->setFieldNameSuffix('mpthankyoupage');
        $fieldset = $form->addFieldset('base_fieldset', [
            'legend'      => __('Display'),
            'collapsable' => true
        ]);

        $newsletterImage = $this->newsletterStyle->getImageUrls();
        $orderImage      = $this->orderStyle->getImageUrls();
        $styleValues     = ($pagetype === Pagetype::NEWSLETTER) ?
            $this->newsletterStyle->toOptionArray() :
            $this->orderStyle->toOptionArray();
        if (!$model->getId()) {
            $defaultImage = ($pagetype === Pagetype::NEWSLETTER) ? $newsletterImage['style1'] : $orderImage['simple'];
            $demoImage    = '<img src="' . $defaultImage . '" alt="demo"  class="img-responsive" id="demo-image">';
        } else {
            $defaultImage = ($pagetype === Pagetype::NEWSLETTER) ?
                $newsletterImage[$model->getStyle()] :
                $orderImage[$model->getStyle()];
            $demoImage    = '<img src="' . $defaultImage . '" alt="demo"  class="img-responsive" id="demo-image">';
        }

        $fieldset->addField('style', 'select', [
            'name'   => 'style',
            'label'  => __('Select Style'),
            'title'  => __('Select Style'),
            'values' => $styleValues,
            'note'   => $demoImage
        ]);

        if ($pagetype == Pagetype::ORDER) {
            $customStyle = $fieldset->addField('custom_style', 'select', [
                'name'   => 'custom_style',
                'label'  => __('Custom Style'),
                'title'  => __('Custom Style'),
                'values' => $this->customStyle->toOptionArray(),
                'note'   => '<a id="load-template" class="btn" style="display:none;">' . __('Load Template') . '</a>'
            ]);
        }

        $title = $fieldset->addField('title', 'text', [
            'name'  => 'title',
            'label' => __('Title'),
            'title' => __('Title')
        ]);

        $sub_title = $fieldset->addField('sub_title', 'text', [
            'name'  => 'sub_title',
            'label' => __('Sub-title'),
            'title' => __('Sub-title')
        ]);

        $description = $fieldset->addField('description', 'textarea', [
            'name'  => 'description',
            'label' => __('Description'),
            'title' => __('Description'),
            'note'  => '<div class="admin__field-control control"><a class="insert-variable" class="btn">' . __('Insert Variable...') . '</a></div>'
        ]);

        if ($pagetype == Pagetype::ORDER) {
            $continueBtn = $fieldset->addField('continue_button', 'select', [
                'name'   => 'continue_button',
                'label'  => __('Show Continue Shopping Button'),
                'title'  => __('Show Continue Shopping Button'),
                'values' => $this->statusOptions->toOptionArray()
            ]);

            $enableBlock = $fieldset->addField('block', 'multiselect', [
                'name'   => 'block',
                'label'  => __('Enable Block(s)'),
                'title'  => __('Enable Block(s)'),
                'values' => $this->orderBlock->toOptionArray()
            ]);

            $customHtml = $fieldset->addField('custom_html', 'textarea', [
                'name'  => 'custom_html',
                'label' => __('Page HTML'),
                'title' => __('Page HTML'),
                'note'  => '<div class="admin__field-control control"><a class="insert-variable" class="btn">' . __('Insert Variable...') . '</a></div>'
            ]);

            $customCss = $fieldset->addField('custom_css', 'textarea', [
                'name'  => 'custom_css',
                'label' => __('Custom CSS'),
                'title' => __('Custom CSS'),
                'note'  => __('This CSS will be applied on the order success page. You can change color, position, font, etc.') .
                    '<div class="admin__field-control control" style="display:none;"><a class="insert-variable" class="btn">' . __('Insert Variable...') . '</a></div>'
            ]);

            if ($hasHyvaTheme) {
                $hyvaCustomHtml = $fieldset->addField('hyva_custom_html', 'textarea', [
                    'name'  => 'hyva_custom_html',
                    'label' => __('Page HTML for Hyva Theme'),
                    'title' => __('Page HTML for Hyva Theme'),
                    'note'  => '<div class="admin__field-control control"><a class="insert-variable" class="btn">' . __('Insert Variable...') . '</a></div>'
                ]);

                $hyvaCustomCss = $fieldset->addField('hyva_custom_css', 'textarea', [
                    'name'  => 'hyva_custom_css',
                    'label' => __('Custom CSS for Hyva Theme'),
                    'title' => __('Custom CSS for Hyva Theme'),
                    'note'  => __('This CSS will be applied on the order success page. You can change color, position, font, etc.') .
                        '<div class="admin__field-control control" style="display:none;"><a class="insert-variable" class="btn">' . __('Insert Variable...') . '</a></div>'
                ]);
            }

            $fieldset->addField('static_block_1', 'select', [
                'name'   => 'static_block_1',
                'label'  => __('Static Block 1'),
                'title'  => __('Static Block 1'),
                'values' => $this->staticBlock->toOptionArray()
            ]);

            $fieldset->addField('static_block_2', 'select', [
                'name'   => 'static_block_2',
                'label'  => __('Static Block 2'),
                'title'  => __('Static Block 2'),
                'values' => $this->staticBlock->toOptionArray()
            ]);
        } else {
            $fieldset->addField('social_share', 'select', [
                'name'   => 'social_share',
                'label'  => __('Show Social Share'),
                'title'  => __('Show Social Share'),
                'values' => $this->statusOptions->toOptionArray()
            ]);

            $fieldset->addField('static_block_1', 'select', [
                'name'   => 'static_block_1',
                'label'  => __('Static Block'),
                'title'  => __('Static Block'),
                'values' => $this->staticBlock->toOptionArray()
            ]);

            $fieldset->addField('product_slider_id', 'select', [
                'name'   => 'product_slider_id',
                'label'  => __('Insert Product Slider'),
                'title'  => __('Insert Product Slider'),
                'values' => $this->productSlider->toOptionArray(),
                'note'   => __(
                    'To enable this function, please install Mageplaza Product Slider <a href="%1" target="_bank">here.</a>',
                    'https://www.mageplaza.com/magento-2-product-slider-extension/'
                )
            ]);

            $fieldset->addField('custom_css', 'textarea', [
                'name'  => 'custom_css',
                'label' => __('Custom CSS'),
                'title' => __('Custom CSS'),
                'note'  => __('This CSS will be applied on the subscribe success page. You can change color, position, font, etc.') .
                    '<div class="admin__field-control control" style="display:none;"><a class="insert-variable" class="btn"></a><a class="insert-variable" class="btn"></a></div>'
            ]);

            if ($hasHyvaTheme) {
                $hyvaCustomCss = $fieldset->addField('hyva_custom_css', 'textarea', [
                    'name'  => 'hyva_custom_css',
                    'label' => __('Custom CSS for Hyva Theme'),
                    'title' => __('Custom CSS for Hyva Theme'),
                    'note'  => __('This CSS will be applied on the subscribe success page. You can change color, position, font, etc.') .
                        '<div class="admin__field-control control" style="display:none;"><a class="insert-variable" class="btn">' . __('Insert Variable...') . '</a></div>'
                ]);
            }
        }

        //        Config Coupon Block
        $sub_fieldset_1 = $form->addFieldset('sub_fieldset_1', [
            'legend'      => __('Coupon Block'),
            'collapsable' => true
        ]);

        $enableCoupon = $sub_fieldset_1->addField('enable_coupon', 'select', [
            'name'   => 'enable_coupon',
            'label'  => __('Enable'),
            'title'  => __('Enable'),
            'values' => $this->statusOptions->toOptionArray()
        ]);

        $ruleId = $sub_fieldset_1->addField('rule_id', 'select', [
            'name'   => 'rule_id',
            'label'  => __('Select Rule'),
            'title'  => __('Select Rule'),
            'values' => $this->cartRules->toOptionArray(),
            'note'   => __('Go to Marketing > Cart Price Rules to generate a rule.') . '<br>' .
                __('The rule should be set as a Specific Coupon and Auto-generated.') . '<br>
                            <a href="https://www.mageplaza.com/kb/how-create-coupon-codes-in-magento-2.html" target="_blank">' . __('Learn more') . '</a>'
        ]);

        $couponPattern = $sub_fieldset_1->addField('coupon_pattern', 'text', [
            'name'  => 'coupon_pattern',
            'label' => __('Coupon Pattern'),
            'title' => __('Coupon Pattern'),
            'note'  => __('A coupon code pattern follows this rule:') . ' </br>
                    <strong>[4A]</strong> - ' . __('4 alpha characters') . '</br><strong>[4N]</strong> - ' . __('4 numeric characters') . '</br><strong>[4AN]</strong> - ' . __('4 alphanumeric characters') . ' </br></br>
                    Eg: GIFT-[4AN]-[3A]-[5N] </br>=> <strong>GIFT-J34T-OEC-54354</strong> </br>'
        ]);

        $couponLabel = $sub_fieldset_1->addField('coupon_label', 'text', [
            'name'  => 'coupon_label',
            'label' => __('Coupon Label'),
            'title' => __('Coupon Label'),
            'note'  => '<div class="admin__field-control control"><a class="insert-variable" class="btn">' . __('Insert Variable...') . '</a></div>'
        ]);

        if ($pagetype == Pagetype::ORDER) {
            //        Config Product Slider
            $sub_fieldset_2 = $form->addFieldset('sub_fieldset_2', [
                'legend'      => __('Product Slider Block'),
                'collapsable' => true
            ]);

            $enableSlider = $sub_fieldset_2->addField('enable_product_slider', 'select', [
                'name'   => 'enable_product_slider',
                'label'  => __('Enable'),
                'title'  => __('Enable'),
                'values' => $this->statusOptions->toOptionArray(),
                'note'   => __(
                    'The cross-selling product list will display based on the products in the orders. To set cross-selling products, please visit <a href="%1" target="_blank">here</a>.',
                    'https://www.mageplaza.com/kb/how-to-configure-related-up-sell-cross-sell-products-in-magento-2.html'
                )
            ]);

            $sliderTitle = $sub_fieldset_2->addField('product_slider_title', 'text', [
                'name'  => 'product_slider_title',
                'label' => __('Title'),
                'title' => __('Title')
            ]);

            $sliderLimit = $sub_fieldset_2->addField('product_slider_limit', 'text', [
                'name'  => 'product_slider_limit',
                'label' => __('Limit the number of products'),
                'title' => __('Limit the number of products'),
                'class' => 'validate-not-negative-number',
                'note'  => __('Number of Cross-Sell products will be shown. Default: 10')
            ]);

            $sliderAdditional = $sub_fieldset_2->addField('product_slider_additional', 'multiselect', [
                'name'   => 'product_slider_additional',
                'label'  => __('Display Information'),
                'title'  => __('Display Information'),
                'values' => $this->sliderAdditional->toOptionArray()
            ]);
        }

        //        Config Mageplaza FAQ
        $sub_fieldset_3 = $form->addFieldset('sub_fieldset_3', [
            'legend'      => __('FAQ Block'),
            'collapsable' => true
        ]);

        $enableF = $sub_fieldset_3->addField('enable_faq', 'select', [
            'name'   => 'enable_faq',
            'label'  => __('Enable'),
            'title'  => __('Enable'),
            'values' => $this->enableFaq->toOptionArray(),
            'note'   => __(
                'Please install/enable Mageplaza_FAQ <a href="%1" target="_bank">here</a> to enable this feature',
                'https://marketplace.magento.com/mageplaza-module-faqs.html'
            )
        ]);

        $faqTitle = $sub_fieldset_3->addField('faq_title', 'text', [
            'name'  => 'faq_title',
            'label' => __('Title'),
            'title' => __('Title')
        ]);

        $faqCat = $sub_fieldset_3->addField('faq_category', 'select', [
            'name'   => 'faq_category',
            'label'  => __('Select Category'),
            'title'  => __('Select Category'),
            'values' => $this->faqCategory->toOptionArray()
        ]);

        $faqLimit = $sub_fieldset_3->addField('faq_limit', 'text', [
            'name'  => 'faq_limit',
            'label' => __('Limit'),
            'title' => __('Limit'),
            'note'  => __('Number of FAQs articles will be shown. Default: 5')
        ]);

        $dependencies = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Form\Element\Dependence');
        if ($pagetype == Pagetype::ORDER) {
            $dependencies->addFieldMap($customStyle->getHtmlId(), $customStyle->getName())
                ->addFieldMap($title->getHtmlId(), $title->getName())
                ->addFieldMap($sub_title->getHtmlId(), $sub_title->getName())
                ->addFieldMap($description->getHtmlId(), $description->getName())
                ->addFieldMap($continueBtn->getHtmlId(), $continueBtn->getName())
                ->addFieldMap($enableBlock->getHtmlId(), $enableBlock->getName())
                ->addFieldMap($customHtml->getHtmlId(), $customHtml->getName())
                ->addFieldMap($customCss->getHtmlId(), $customCss->getName())
                ->addFieldMap($couponLabel->getHtmlId(), $couponLabel->getName())
                ->addFieldMap($enableSlider->getHtmlId(), $enableSlider->getName())
                ->addFieldMap($sliderTitle->getHtmlId(), $sliderTitle->getName())
                ->addFieldMap($sliderLimit->getHtmlId(), $sliderLimit->getName())
                ->addFieldMap($sliderAdditional->getHtmlId(), $sliderAdditional->getName())
                ->addFieldDependence($title->getName(), $customStyle->getName(), '0')
                ->addFieldDependence($sub_title->getName(), $customStyle->getName(), '0')
                ->addFieldDependence($description->getName(), $customStyle->getName(), '0')
                ->addFieldDependence($continueBtn->getName(), $customStyle->getName(), '0')
                ->addFieldDependence($enableBlock->getName(), $customStyle->getName(), '0')
                ->addFieldDependence($customHtml->getName(), $customStyle->getName(), '1')
                ->addFieldDependence($customCss->getName(), $customStyle->getName(), '1')
                ->addFieldDependence($couponLabel->getName(), $customStyle->getName(), '0')
                ->addFieldDependence($sliderTitle->getName(), $enableSlider->getName(), '1')
                ->addFieldDependence($sliderLimit->getName(), $enableSlider->getName(), '1')
                ->addFieldDependence($sliderAdditional->getName(), $enableSlider->getName(), '1');
            if ($hasHyvaTheme) {
                $dependencies->addFieldMap($hyvaCustomHtml->getHtmlId(), $hyvaCustomHtml->getName())
                    ->addFieldMap($hyvaCustomCss->getHtmlId(), $hyvaCustomCss->getName())
                    ->addFieldDependence($hyvaCustomHtml->getName(), $customStyle->getName(), '1')
                    ->addFieldDependence($hyvaCustomCss->getName(), $customStyle->getName(), '1');
            }
        }
        $dependencies->addFieldMap($enableCoupon->getHtmlId(), $enableCoupon->getName())
            ->addFieldMap($ruleId->getHtmlId(), $ruleId->getName())
            ->addFieldMap($couponPattern->getHtmlId(), $couponPattern->getName())
            ->addFieldMap($couponLabel->getHtmlId(), $couponLabel->getName())
            ->addFieldMap($enableF->getHtmlId(), $enableF->getName())
            ->addFieldMap($faqTitle->getHtmlId(), $faqTitle->getName())
            ->addFieldMap($faqCat->getHtmlId(), $faqCat->getName())
            ->addFieldMap($faqLimit->getHtmlId(), $faqLimit->getName())
            ->addFieldDependence($ruleId->getName(), $enableCoupon->getName(), '1')
            ->addFieldDependence($couponPattern->getName(), $enableCoupon->getName(), '1')
            ->addFieldDependence($couponLabel->getName(), $enableCoupon->getName(), '1')
            ->addFieldDependence($faqTitle->getName(), $enableF->getName(), '1')
            ->addFieldDependence($faqCat->getName(), $enableF->getName(), '1')
            ->addFieldDependence($faqLimit->getName(), $enableF->getName(), '1');
        // define field dependencies
        $this->setChild('form_after', $dependencies);

        $templateData  = $this->_session->getData('mpthankyoupage_template_data', true);
        $defaultValues = ($pagetype === Pagetype::NEWSLETTER) ?
            $model->getNewsletterDefaultValues() :
            $model->getOrderDefaultValues();
        if ($templateData) {
            $model->addData($templateData);
        } elseif (!$model->getId()) {
            $model->addData($defaultValues);
        }

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @return array
     */
    public function getTemplateVar()
    {
        return $this->templateVar->getTemplateVars();
    }

    /**
     * @return false|string
     */
    public function getImageUrls()
    {
        $ImgUrls = array_merge($this->orderStyle->getImageUrls(), $this->newsletterStyle->getImageUrls());

        return Data::jsonEncode($ImgUrls);
    }

    /**
     * @return string
     */
    public function getModifier()
    {
        return $this->helperData->getModifier();
    }

    /**
     * from Magento Core
     *
     * @param string $string
     *
     * @return string|string[]|null
     */
    public function escapeJs($string)
    {
        if ($string === '' || ctype_digit($string)) {
            return $string;
        }

        return preg_replace_callback(
            '/[^a-z0-9,\._]/iSu',
            function ($matches) {
                $chr = $matches[0];
                if (strlen($chr) != 1) {
                    $chr = mb_convert_encoding($chr, 'UTF-16BE', 'UTF-8');
                    $chr = ($chr === false) ? '' : $chr;
                }

                return sprintf('\\u%04s', strtoupper(bin2hex($chr)));
            },
            $string
        );
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Display');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }
}
