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


namespace Mirasvit\Seo\Block\Adminhtml\System\Config\CanonicalLayered;


use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\View\Element\Context;
use Mirasvit\Seo\Model\Config\Source\AssociatedCanonical\CanonicalLayeredUsageSource;

class UsageSelect extends Select
{
    protected $usageSource;

    public function __construct(
        CanonicalLayeredUsageSource $usageSource,
        Context $context,
        array $data = []
    )  {
        $this->usageSource = $usageSource;

        parent::__construct($context, $data);
    }

    public function setInputName(string $value): Select
    {
        return $this->setName($value);
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            foreach ($this->prepareOptions() as $code => $label) {
                $this->addOption($code, addslashes((string)$label));
            }
        }

        return parent::_toHtml();
    }

    protected function prepareOptions(): array
    {
        $options = [];

        foreach ($this->usageSource->toOptionArray() as $option) {
            $options[$option['value']] = $option['label'];
        }

        return $options;
    }

    /**
     * @param array{value: string, label: string} $option
     * @param bool|false $selected
     * @return string
     */
    protected function _optionToHtml($option, $selected = false)
    {
        $selectedHtml = $selected ? ' selected="selected"' : '';
        if ($this->getIsRenderToJsTemplate() === true) {
            $selectedHtml .= ' <%= option_extra_attrs.option_' . self::calcOptionHash($option['value']) . ' %>';
        }
        $html = '<option value="' . $this->escapeHtml($option['value']) . '"' . $selectedHtml . '>'
            . $this->escapeHtml($option['label']) .
            '</option>';

        return $html;
    }

    /**
     * @param string $optionValue
     * @return string
     */
    public function calcOptionHash($optionValue)
    {
        return sprintf('%u', crc32($this->getName() . $this->getId() . $optionValue));
    }
}
