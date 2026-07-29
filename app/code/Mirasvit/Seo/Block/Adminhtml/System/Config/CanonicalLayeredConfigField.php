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


namespace Mirasvit\Seo\Block\Adminhtml\System\Config;


use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;


class CanonicalLayeredConfigField extends AbstractFieldArray
{
    protected $attributesRenderer;

    protected $usageRenderer;

    protected function _construct()
    {
        $this->_addAfter = false;

        $this->addColumn(
            'attribute',
            ['label' => __('Attribute'), 'renderer' => $this->getAttributesRenderer()]
        );

        $this->addColumn(
            'usage',
            ['label' => __('Usage'), 'renderer' => $this->getUsageRenderer()]
        );

        parent::_construct();
    }

    protected function getAttributesRenderer()
    {
        if (!$this->attributesRenderer) {
            $this->attributesRenderer = $this->getLayout()->createBlock(
                \Mirasvit\Seo\Block\Adminhtml\System\Config\CanonicalLayered\AttributeSelect::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->attributesRenderer->setClass('customer_options_select');
        }

        return $this->attributesRenderer;
    }

    protected function getUsageRenderer()
    {
        if (!$this->usageRenderer) {
            $this->usageRenderer = $this->getLayout()->createBlock(
                \Mirasvit\Seo\Block\Adminhtml\System\Config\CanonicalLayered\UsageSelect::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->usageRenderer->setClass('customer_options_select');
        }

        return $this->usageRenderer;
    }

    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $options = [];
        if ($row->getData('attribute')) {
            $options['option_' . $this->getAttributesRenderer()->calcOptionHash($row->getData('attribute'))]
                = 'selected="selected"';
        }

        if ($row->getData('usage')) {
            $options['option_' . $this->getUsageRenderer()->calcOptionHash($row->getData('usage'))]
                = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }
}
