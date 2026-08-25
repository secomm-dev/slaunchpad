<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\AiDiscoverability\Model\Config\CategoryTreeProvider;

/**
 * Hierarchical category selector for the AI Discoverability config section
 * (SPEC-TASK-5TGJ7V).
 *
 * Renders the field's native hidden input (so config save/load and Use Default /
 * Use Website inheritance keep working untouched) plus a jstree checkbox tree
 * driven by the CORE component Magento_Catalog/js/category-checkbox-tree
 * (requirejs alias `categoryCheckboxTree`) — the same tree widget the core
 * promo-rule category chooser renders via
 * Magento\Catalog\Block\Adminhtml\Category\Checkboxes\Tree. The core JS writes
 * checked ids (sorted, comma-joined) into window[jsFormObject].updateElement,
 * so the block exposes the hidden input under that tiny bridge object.
 */
class CategoryTree extends Field
{
    /**
     * Core jstree widget writes selections into window[jsFormObject].updateElement.
     */
    private const JS_FORM_OBJECT = 'seocommAiCategoriesForm';

    /**
     * @var CategoryTreeProvider scoped category tree provider
     */
    private readonly CategoryTreeProvider $treeProvider;

    /**
     * @var Json JSON encoder for the tree init config
     */
    private readonly Json $jsonSerializer;

    /**
     * @param Context $context backend context
     * @param CategoryTreeProvider $treeProvider scoped category tree provider
     * @param Json $jsonSerializer JSON encoder
     * @param array $data block data
     */
    public function __construct(
        Context $context,
        CategoryTreeProvider $treeProvider,
        Json $jsonSerializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->treeProvider = $treeProvider;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Hidden input (native config element) + core jstree checkbox tree.
     *
     * @param AbstractElement $element config form element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        // Native hidden input under the element's real name → config save/load
        // and scope inheritance behave exactly like any core config field.
        $inputId = $element->getHtmlId();
        $value = $element->getValue() !== null ? (string) $element->getValue() : '';
        $html = sprintf(
            '<input type="hidden" id="%s" name="%s" value="%s" />',
            $this->escapeHtml($inputId),
            $this->escapeHtml($element->getName()),
            $this->escapeHtml($value)
        );

        $divId = $inputId . '_jstree';
        $initConfig = [
            'dataUrl' => '',
            'divId' => $divId,
            'rootVisible' => false,
            'useAjax' => false,
            'currentNodeId' => 0,
            'jsFormObject' => self::JS_FORM_OBJECT,
            'name' => '',
            'checked' => '',
            'allowDrop' => false,
            'allowdDrop' => false,
            'rootId' => 0,
            'expanded' => true,
            'categoryId' => 0,
            'treeJson' => $this->treeProvider->getTree(),
        ];
        $initJson = $this->jsonSerializer->serialize($initConfig);

        $html .= '<div class="seocomm-category-tree">'
            . '<div id="' . $this->escapeHtml($divId) . '" class="tree"></div></div>'
            . '<script>'
            . 'window.' . self::JS_FORM_OBJECT . ' = {updateElement: document.getElementById('
            . $this->jsonSerializer->serialize($inputId)
            . ')};'
            . '</script>'
            . '<script type="text/x-magento-init">'
            . '{"*": {"categoryCheckboxTree": ' . $initJson . '}}'
            . '</script>';

        return $html;
    }
}
