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

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * @method getAttribute()
 * @method getJsFormObject()
 */
class LandingCondition extends AbstractCondition
{
    private $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context                $context,
        array                  $data = []
    ) {
        $this->objectManager = $objectManager;

        parent::__construct($context, $data);
    }

    public function loadAttributeOptions(): LandingCondition
    {
        $attributes = [
            'landing_page_id' => (string)__('Landing Page'),
        ];

        $this->setData('attribute_option', $attributes);

        return $this;
    }

    public function getExplicitApply(): bool
    {
        return $this->getAttribute() === 'landing_page_id';
    }

    public function getValueElementType(): string
    {
        if ($this->getAttribute() === 'landing_page_id') {
            return 'multiselect';
        }

        return parent::getValueElementType();
    }

    public function getValueSelectOptions(): array
    {
        $options = [];

        if (
            !$this->hasData('value_select_options')
            && $this->getAttribute() === 'landing_page_id'
            && class_exists('\Mirasvit\LandingPage\Repository\PageRepository')
        ) {
            $pageRepository = $this->objectManager->get('\Mirasvit\LandingPage\Repository\PageRepository');

            foreach ($pageRepository->getCollection() as $page) {
                $options[] = [
                    'label' => __($page->getName()),
                    'value' => $page->getPageId(),
                ];
            }

            $this->setData('value_select_options', $options);
        }

        return $options;
    }

    public function validate(AbstractModel $model): bool
    {
        if ($this->getAttribute() === 'landing_page_id') {
            return $this->validateAttribute($model->getPageId());
        }

        return false;
    }
}
