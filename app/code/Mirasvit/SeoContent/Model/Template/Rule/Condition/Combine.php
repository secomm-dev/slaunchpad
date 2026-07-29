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

namespace Mirasvit\SeoContent\Model\Template\Rule\Condition;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Registry;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Magento\Rule\Model\Condition\Context;

class Combine extends \Magento\Rule\Model\Condition\Combine
{
    private $categoryCondition;

    private $pageCondition;

    private $productCondition;

    private $blogCondition;

    private $brandCondition;

    private $landingCondition;

    private $request;

    private $moduleManager;

    private $registry;

    private $ruleType;

    public function __construct(
        CategoryCondition $categoryCondition,
        PageCondition     $pageCondition,
        ProductCondition  $productCondition,
        BlogCondition     $blogCondition,
        BrandCondition    $brandCondition,
        LandingCondition  $landingCondition,
        RequestInterface  $request,
        Manager           $moduleManager,
        Registry          $registry,
        Context           $context,
        array             $data = []
    ) {
        parent::__construct($context, $data);

        $this->categoryCondition = $categoryCondition;
        $this->pageCondition     = $pageCondition;
        $this->productCondition  = $productCondition;
        $this->blogCondition     = $blogCondition;
        $this->brandCondition    = $brandCondition;
        $this->landingCondition  = $landingCondition;
        $this->request           = $request;
        $this->moduleManager     = $moduleManager;
        $this->registry          = $registry;

        $this->setData('type', self::class);
    }

    /**
     * @param int|string|null $type
     * @return $this
     */
    public function setRuleType($type): Combine
    {
        $this->ruleType = $type;

        return $this;
    }

    /**
     * @return array
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getNewChildSelectOptions(): array
    {
        $productAttributes  = $this->productCondition->loadAttributeOptions()->getData('attribute_option');
        $categoryAttributes = $this->categoryCondition->loadAttributeOptions()->getData('attribute_option');
        $pageAttributes     = $this->pageCondition->loadAttributeOptions()->getData('attribute_option');
        $blogAttributes     = $this->blogCondition->loadAttributeOptions()->getData('attribute_option');
        $brandAttributes    = $this->brandCondition->loadAttributeOptions()->getData('attribute_option');
        $landingAttributes  = $this->landingCondition->loadAttributeOptions()->getData('attribute_option');

        $attributes = [];

        foreach ($productAttributes as $code => $label) {
            $attributes['product'][] = [
                'value' => ProductCondition::class . '|' . $code,
                'label' => $label
            ];
        }

        foreach ($categoryAttributes as $code => $label) {
            $attributes['category'][] = [
                'value' => CategoryCondition::class . '|' . $code,
                'label' => $label
            ];
        }

        foreach ($pageAttributes as $code => $label) {
            $attributes['page'][] = [
                'value' => PageCondition::class . '|' . $code,
                'label' => $label
            ];
        }

        if ($this->moduleManager->isEnabled('Mirasvit_BlogMx')) {
            foreach ($blogAttributes as $code => $label) {
                $attributes['blog'][] = [
                    'value' => BlogCondition::class . '|' . $code,
                    'label' => $label
                ];
            }
        }

        if ($this->moduleManager->isEnabled('Mirasvit_Brand')) {
            foreach ($brandAttributes as $code => $label) {
                $attributes['brand'][] = [
                    'value' => BrandCondition::class . '|' . $code,
                    'label' => $label
                ];
            }
        }

        if ($this->moduleManager->isEnabled('Mirasvit_LandingPage')) {
            foreach ($landingAttributes as $code => $label) {
                $attributes['landing'][] = [
                    'value' => LandingCondition::class . '|' . $code,
                    'label' => $label
                ];
            }
        }

        $conditions = parent::getNewChildSelectOptions();
        $ruleType   = $this->ruleType ? (int)$this->ruleType : (int)$this->request->getParam('rule_type');

        if (!$ruleType) {
            $template = $this->registry->registry(TemplateInterface::class);
            $ruleType = (int)$template->getRuleType();
        }

        if ($ruleType) {
            $conditions = array_merge_recursive($conditions, [
                [
                    'value' => self::class,
                    'label' => __('Conditions combination'),
                ],
            ]);
        }

        if ($ruleType === TemplateInterface::RULE_TYPE_NAVIGATION) {
            $conditions = array_merge_recursive($conditions, [
                [
                    'value' => Filter::class,
                    'label' => __('Filter Subselection')
                ],
            ]);
        }

        if (in_array($ruleType, [TemplateInterface::RULE_TYPE_CATEGORY, TemplateInterface::RULE_TYPE_NAVIGATION])) {
            $conditions = array_merge_recursive($conditions, [
                [
                    'label' => __('Category Attributes'),
                    'value' => $attributes['category'] ?? [],
                ],
            ]);
        }

        if (in_array($ruleType, [TemplateInterface::RULE_TYPE_PRODUCT, TemplateInterface::RULE_TYPE_NAVIGATION])) {
            $conditions = array_merge_recursive($conditions, [
                [
                    'label' => __('Product attributes'),
                    'value' => $attributes['product'] ?? [],
                ],
            ]);
        }

        if ($ruleType === TemplateInterface::RULE_TYPE_PAGE) {
            $conditions = [
                [
                    'label' => __('Page attributes'),
                    'value' => $attributes['page'] ?? [],
                ],
            ];
        }

        if ($this->moduleManager->isEnabled('Mirasvit_BlogMx')) {
            if ($ruleType === TemplateInterface::RULE_TYPE_BLOG) {
                $conditions = [
                    [
                        'label' => __('Blog Attributes'),
                        'value' => $attributes['blog'] ?? [],
                    ],
                ];
            }
        }

        if ($this->moduleManager->isEnabled('Mirasvit_Brand')) {
            if ($ruleType === TemplateInterface::RULE_TYPE_BRAND) {
                $conditions = [
                    [
                        'label' => __('Brand Attributes'),
                        'value' => $attributes['brand'] ?? [],
                    ],
                ];
            }
        }

        if ($this->moduleManager->isEnabled('Mirasvit_LandingPage')) {
            if ($ruleType === TemplateInterface::RULE_TYPE_LANDING) {
                $conditions = [
                    [
                        'label' => __('Landing Page Attributes'),
                        'value' => $attributes['landing'] ?? [],
                    ],
                ];
            }
        }

        return $conditions;
    }

    /**
     * @param mixed $productCollection
     *
     * @return $this
     */
    public function collectValidatedAttributes($productCollection): Combine
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }

        return $this;
    }
}
