<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Block\Adminhtml\Widget;

use Magento\Backend\Block\Template;
use Magento\Cms\Model\Wysiwyg\Config as WysiwygConfig;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\UiWidget\Api\ComponentRegistryInterface;

/**
 * Adds the registry-driven component editor beside the persisted payload field.
 */
class ComponentOptions extends Template
{
    /** @var string */
    protected $_template = 'Secomm_UiWidget::widget/component-options.phtml';

    /**
     * @param Template\Context $context Template context.
     * @param ComponentRegistryInterface $registry Component registry.
     * @param Json $json JSON serializer.
     * @param array $data Block data.
     */
    public function __construct(
        Template\Context $context,
        private readonly ComponentRegistryInterface $registry,
        private readonly Json $json,
        private readonly WysiwygConfig $wysiwygConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Turn Magento's parameter element into the codec-backed options editor.
     *
     * @param AbstractElement $element Payload form element.
     */
    public function prepareElementHtml(AbstractElement $element): AbstractElement
    {
        $this->setData('payload_element_id', $element->getHtmlId());
        $this->setData('payload_element_name', $element->getName());
        $this->setData('payload_element_value', (string)$element->getValue());
        $element->setValue('');
        $element->setData('after_element_html', $this->toHtml());

        return $element;
    }

    /**
     * Return safe Admin schema JSON from enabled registry definitions.
     */
    public function getSchemasJson(): string
    {
        $schemas = [];
        foreach ($this->registry->getAll() as $definition) {
            $schemas[$definition->getId()] = [
                'schemaVersion' => $definition->getSchemaVersion(),
                'fields' => $definition->getFields(),
            ];
        }

        return $this->json->serialize($schemas);
    }

    /**
     * Return the native Magento media chooser base URL.
     */
    public function getMediaBrowserUrl(): string
    {
        return $this->getUrl('cms/wysiwyg_images/index');
    }

    /**
     * Return Magento's native editor configuration for trusted CMS fields.
     */
    public function getWysiwygConfigJson(): string
    {
        return $this->json->serialize($this->wysiwygConfig->getConfig([
            'add_variables' => false,
            'add_widgets' => false,
            'add_directives' => true,
            'use_container' => false,
            'height' => '320px',
        ])->getData());
    }
}
