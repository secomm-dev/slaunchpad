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
class BlogCondition extends AbstractCondition
{
    private $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        array $data = []
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($context, $data);
    }

    public function loadAttributeOptions(): BlogCondition
    {
        $attributes = [
            'category_id' => (string)__('Category'),
            'post_id' => (string)__('Post'),
            'tag_id' => (string)__('Tag'),
            'author_id' => (string)__('Author'),
        ];

        $this->setData('attribute_option', $attributes);

        return $this;
    }

    public function getExplicitApply(): bool
    {
        switch ($this->getAttribute()) {
            case 'category_id':
            case 'post_id':
            case 'tag_id':
            case 'author_id':
                return true;
        }

        return false;
    }

    public function getValueElementType(): string
    {
        switch ($this->getAttribute()) {
            case 'category_id':
            case 'post_id':
            case 'tag_id':
            case 'author_id':
                return 'multiselect';
        }

        return parent::getValueElementType();
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getValueSelectOptions(): array
    {
        $options = [];

        if (
            !$this->hasData('value_select_options')
            && in_array($this->getAttribute(), ['category_id', 'post_id', 'tag_id', 'author_id'])
        ) {
            switch ($this->getAttribute()) {
                case 'category_id':
                    if (class_exists('\Mirasvit\BlogMx\Model\Config\Source\CategorySource')) {
                        $categorySource = $this->objectManager->get('\Mirasvit\BlogMx\Model\Config\Source\CategorySource');
                        $options        = $categorySource->toOptionArray();
                    }

                    break;
                case 'post_id':
                    if (class_exists('\Mirasvit\BlogMx\Repository\PostRepository')) {
                        $postRepository = $this->objectManager->get('\Mirasvit\BlogMx\Repository\PostRepository');

                        foreach ($postRepository->getCollection() as $post) {
                            $options[] = [
                                'label' => __($post->getName()),
                                'value' => $post->getId(),
                            ];
                        }
                    }

                    break;
                case 'tag_id':
                    if (class_exists('\Mirasvit\BlogMx\Model\Config\Source\TagsSource')) {
                        $tagSource = $this->objectManager->get('\Mirasvit\BlogMx\Model\Config\Source\TagsSource');
                        $options   = $tagSource->toOptionArray();
                    }

                    break;
                case 'author_id':
                    if (class_exists('\Mirasvit\BlogMx\Model\Config\Source\AuthorSource')) {
                        $authorSource = $this->objectManager->get('\Mirasvit\BlogMx\Model\Config\Source\AuthorSource');
                        $options      = $authorSource->toOptionArray();
                    }

                    break;
            }

            $this->setData('value_select_options', $options);
        }

        return $options;
    }

    public function validate(AbstractModel $model): bool
    {
        $attributeCode = $this->getAttribute();

        if (
            ($model instanceof \Mirasvit\BlogMx\Api\Data\CategoryInterface && $attributeCode === 'category_id')
            || ($model instanceof \Mirasvit\BlogMx\Api\Data\PostInterface && $attributeCode === 'post_id')
            || ($model instanceof \Mirasvit\BlogMx\Api\Data\TagInterface && $attributeCode === 'tag_id')
            || ($model instanceof \Mirasvit\BlogMx\Api\Data\AuthorInterface && $attributeCode === 'author_id')
        ) {
            $id = [$model->getId()];

            return $this->validateAttribute($id);
        }

        return false;
    }
}
