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

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\Faqs\Block\Category\View;
use Mageplaza\Faqs\Helper\Data;
use Mageplaza\ThankYouPage\Helper\Data as ThankYouPageHelper;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;

/**
 * Class Faq
 * @package Mageplaza\ThankYouPage\Block
 */
class Faq extends Template
{
    /**
     * @var ThankYouPageHelper
     */
    protected $thankyoupageHelper;

    /**
     * Faq constructor.
     *
     * @param Context $context
     * @param ThankYouPageHelper $thankyoupageHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ThankYouPageHelper $thankyoupageHelper,
        array $data = []
    ) {
        $this->thankyoupageHelper = $thankyoupageHelper;

        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _prepareLayout()
    {
        return $this;
    }

    /**
     * @return |null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOrderFaqs()
    {
        $collection     = null;
        $faqCatOrderOpt = $this->thankyoupageHelper->getTemplateData(Pagetype::ORDER);
        if ($faqCatOrderOpt && $faqCatOrderOpt->getEnableFaq() && $faqCatOrderOpt->getFaqCategory()) {
            $faqs       = $this->thankyoupageHelper->createObject(View::class);
            $collection = $faqs->getArticleByCategory($faqCatOrderOpt->getFaqCategory());
        }

        return $collection;
    }

    /**
     * @return bool|mixed|string|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOrderFaqTitle()
    {
        $orderTemplate = $this->thankyoupageHelper->getTemplateData(Pagetype::ORDER);

        return !$orderTemplate ?: $orderTemplate->getFaqTitle();
    }

    /**
     * @return bool|int|mixed|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOrderFaqLimit()
    {
        $orderTemplate = $this->thankyoupageHelper->getTemplateData(Pagetype::ORDER);

        return !$orderTemplate ?: $orderTemplate->getFaqLimit();
    }

    /**
     * @return |null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNewsletterFaqs()
    {
        $collection          = null;
        $faqCatNewsletterOpt = $this->thankyoupageHelper->getTemplateData(Pagetype::NEWSLETTER);
        if ($faqCatNewsletterOpt !== null &&
            $faqCatNewsletterOpt->getEnableFaq() &&
            $faqCatNewsletterOpt->getFaqCategory()) {
            $faqs       = $this->thankyoupageHelper->createObject(View::class);
            $collection = $faqs->getArticleByCategory($faqCatNewsletterOpt->getFaqCategory());
        }

        return $collection;
    }

    /**
     * @return bool|mixed|string|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNewsletterFaqTitle()
    {
        $newsletterTemplate = $this->thankyoupageHelper->getTemplateData(Pagetype::NEWSLETTER);

        return !$newsletterTemplate ?: $newsletterTemplate->getFaqTitle();
    }

    /**
     * @return bool|int|mixed|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNewsletterFaqLimit()
    {
        $newsletterTemplate = $this->thankyoupageHelper->getTemplateData(Pagetype::NEWSLETTER);

        return !$newsletterTemplate ?: $newsletterTemplate->getFaqLimit();
    }

    /**
     * get FAQ helper data
     * @return mixed
     */
    public function getHelperData()
    {
        return $this->thankyoupageHelper->createObject(Data::class);
    }

    /**
     * Get question display type
     *
     * @return mixed
     */
    public function isCollapsible()
    {
        return $this->getHelperData()->getFaqsPageConfig('question_style') == 1;
    }

    /**
     * @return mixed
     */
    public function isOrderSuccessPageEnable()
    {
        return $this->thankyoupageHelper->isOrderSuccessPageEnable();
    }
}
