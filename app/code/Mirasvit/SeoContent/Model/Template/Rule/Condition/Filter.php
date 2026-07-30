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

use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\LayeredNavigation\Block\Navigation\State;
use Magento\Rule\Model\Condition\Context;

class Filter extends \Magento\Rule\Model\Condition\Combine
{
    private $productFactory;

    private $state;

    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        State $state,
        array $data = []
    ) {
        $this->productFactory = $productFactory;
        $this->state          = $state;

        parent::__construct($context, $data);
        $this->setType(self::class)->setValue(null);
    }

    public function getNewChildSelectOptions(): array
    {
        $conditions        = parent::getNewChildSelectOptions();
        $productAttributes = $this->getProductAttributes();
        $attributes        = [];

        foreach ($productAttributes as $code => $label) {
            $attributes[] = [
                'value' => ProductCondition::class . '|' . $code,
                'label' => $label,
            ];
        }

        return array_merge_recursive(
            $conditions,
            [
                ['label' => __('Product attribute'), 'value' => $attributes]
            ]
        );
    }

    public function collectValidatedAttributes($productCollection)
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }

        return $this;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _isValid($entity): bool
    {
        $activeFilters = $this->state->getActiveFilters();
        $all           = $this->getAggregator() === 'all';

        if ($this->validateAttribute(count($activeFilters))) {
            if (!$this->getConditions()) {
                return true;
            }

            $validatedConditions = 0;

            foreach ($this->getConditions() as $cond) {
                foreach ($activeFilters as $activeFilter) {
                    if ($activeFilter->getFilter()->getAttributeModel()->getAttributeCode() === $cond->getData('attribute')) {
                        $validated = $cond->validateAttribute($activeFilter->getValue());

                        if ($all && !$validated) {
                            return false;
                        } elseif (!$all && $validated) {
                            return true;
                        }

                        $validatedConditions += 1;

                        break;
                    }
                }
            }

            return $all && (count($this->getConditions()) === $validatedConditions);
        }

        return false;
    }

    public function loadArray($arr, $key = 'conditions')
    {
        $this->setOperator($arr['operator']);
        parent::loadArray($arr, $key);

        return $this;
    }

    public function loadValueOptions()
    {
        return $this;
    }

    public function loadOperatorOptions()
    {
        $this->setOperatorOption(
            [
                '==' => __('is'),
                '!=' => __('is not'),
                '>=' => __('equals or greater than'),
                '<=' => __('equals or less than'),
                '>'  => __('greater than'),
                '<'  => __('less than')
            ]
        );

        return $this;
    }

    public function getValueElementType(): string
    {
        return 'text';
    }

    public function asHtml()
    {
        $html = $this->getTypeElement()->getHtml() . __(
                'If number of applied filters %1 %2 while %3 of these Conditions match:',
                $this->getOperatorElement()->getHtml(),
                $this->getValueElement()->getHtml(),
                $this->getAggregatorElement()->getHtml()
            );
        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }

        return $html;
    }

    private function getProductAttributes(): array
    {
        $productAttributes = $this->productFactory->create()->loadAllAttributes()->getAttributesByCode();
        $attributes        = [];

        foreach ($productAttributes as $attribute) {
            if (
                !$attribute->isAllowedForRuleCondition()
                || !$attribute->getDataUsingMethod('is_used_for_promo_rules')
                || !$attribute->getIsFilterable()
                || $attribute->getFrontendInput() === 'price'
            ) {
                continue;
            }
            $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel() . ' (' . $attribute->getAttributeCode() . ')';
        }

        asort($attributes);

        return $attributes;
    }
}
