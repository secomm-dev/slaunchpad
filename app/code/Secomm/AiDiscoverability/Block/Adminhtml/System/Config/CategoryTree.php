<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\AiDiscoverability\Model\Config\CategoryTreeProvider;

/**
 * Product-Edit-style category selector for the AI Discoverability config
 * section (SPEC-TASK-5TGJ7V correction).
 *
 * Renders the field's native hidden input (config save/load and Use Default /
 * Use Website inheritance stay untouched) plus the CORE UI component the
 * Product Edit Categories field uses:
 * Magento_Ui/js/form/element/ui-select with the
 * ui/grid/filters/elements/ui-select template — chips for selected values,
 * dropdown with checkbox category tree, search input, Done close action
 * (core defaults: selectType 'tree', showCheckbox, closeBtn 'Done').
 * Instantiated standalone via the core Magento_Ui/js/core/app bootstrap, the
 * same mechanism core .phtml files use to place UI components outside forms.
 *
 * The only glue is the value bridge: the component's value observable is
 * subscribed to and written (numeric ids only, comma-joined) into the hidden
 * input so the native config form POST is identical to before. No custom JS
 * component, template, or CSS.
 */
class CategoryTree extends Field
{
    /**
     * Standalone UI component scope name.
     */
    private const SCOPE_NAME = 'seocommCategorySelect';

    /**
     * @var CategoryTreeProvider scoped category options provider
     */
    private readonly CategoryTreeProvider $treeProvider;

    /**
     * @var Json JSON encoder
     */
    private readonly Json $jsonSerializer;

    /**
     * @param Context $context backend context
     * @param CategoryTreeProvider $treeProvider scoped category options provider
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
     * Hidden input (native config element) + core ui-select category selector.
     *
     * @param AbstractElement $element config form element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $inputId = $element->getHtmlId();
        $value = $element->getValue() !== null ? (string) $element->getValue() : '';
        $selectedIds = array_values(array_filter(array_map('trim', explode(',', $value)), 'is_numeric'));

        $componentConfig = [
            'component' => 'Magento_Ui/js/form/element/ui-select',
            'template' => 'ui/grid/filters/elements/ui-select',
            // Product Edit Categories field config (Categories.php:279-292).
            'filterOptions' => true,
            'chipsEnabled' => true,
            'disableLabel' => true,
            'levelsVisibility' => '1',
            'options' => $this->treeProvider->getOptions(),
            'value' => array_map('intval', $selectedIds),
        ];

        $html = sprintf(
            '<input type="hidden" id="%s" name="%s" value="%s" />',
            $this->escapeHtml($inputId),
            $this->escapeHtml($element->getName()),
            $this->escapeHtml($value)
        );

        $html .= '<div class="seocomm-category-select" data-bind="scope: \'' . self::SCOPE_NAME . '\'">'
            . '<!-- ko template: getTemplate() --><!-- /ko --></div>'
            . '<script type="text/x-magento-init">'
            . json_encode(
                ['*' => ['Magento_Ui/js/core/app' => ['components' => [self::SCOPE_NAME => $componentConfig]]]],
                JSON_UNESCAPED_SLASHES
            )
            . '</script>'
            . '<script>require([\'uiRegistry\'], function (registry) {'
            . 'registry.get(\'' . self::SCOPE_NAME . '\', function (component) {'
            . 'var input = document.getElementById(' . $this->jsonSerializer->serialize($inputId) . ');'
            . 'component.value.subscribe(function (value) {'
            . 'input.value = Array.isArray(value)'
            . ' ? value.filter(function (id) { return /^\\d+$/.test(String(id)); }).join(\',\')'
            . ' : String(value);'
            . '});});});</script>';

        return $html;
    }
}
