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

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\Config\Source\Page;
use Magento\Framework\Model\AbstractModel;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * @method getAttribute()
 * @method getJsFormObject()
 */
class PageCondition extends AbstractCondition
{
    /**
     * @var Page
     */
    private $pageSource;

    /**
     * @param Page $pageSource
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Page $pageSource,
        Context $context,
        array $data = []
    ) {
        $this->pageSource = $pageSource;
        parent::__construct($context, $data);
    }

    /**
     * @return $this|AbstractCondition
     */
    public function loadAttributeOptions()
    {
        $attributes = [
            'page_id' => (string)__('Page'),
        ];

        $this->setData('attribute_option', $attributes);

        return $this;
    }

    /**
     * @return bool
     */
    public function getExplicitApply(): bool
    {
        return $this->getAttribute() == 'page_id';
    }

    /**
     * @return string
     */
    public function getValueElementType(): string
    {
        return $this->getAttribute() == 'page_id' ? 'multiselect' : parent::getValueElementType();
    }

    /**
     * @return array
     */
    public function getValueSelectOptions(): array
    {
        $options = [];

        if (!$this->hasData('value_select_options') && $this->getAttribute() == 'page_id') {
            $options = $this->pageSource->toOptionArray();
            $this->setData('value_select_options', $options);
        }

        return $options;
    }

    /**
     * @param AbstractModel $model
     *
     * @return bool
     */
    public function validate(AbstractModel $model): bool
    {
        if (!($model instanceof PageInterface)) {
            return true;
        }

        $attributeCode = $this->getAttribute();

        if ($attributeCode == 'page_id') {
            $pageIdentifier = [$model->getIdentifier()];

            return $this->validateAttribute($pageIdentifier);
        }

        return parent::validate($model);
    }
}
