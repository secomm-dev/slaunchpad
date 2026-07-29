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

namespace Mirasvit\SeoContent\Block\Adminhtml\Template;

use Magento\Backend\Block\Template;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Ui\Template\Source\RuleTypeSource;

class Preview extends Template
{
    private $ruleTypeSource;

    public function __construct(RuleTypeSource $ruleTypeSource, Template\Context $context)
    {
        $this->ruleTypeSource = $ruleTypeSource;

        parent::__construct($context);
    }

    public function getRuleTypeLabel(TemplateInterface $template): string
    {
        foreach ($this->ruleTypeSource->toOptionArray() as $option) {
            if ($option['value'] == $template->getRuleType()) {
                return (string)$option['label'];
            }
        }

        return '';
    }

    public function prepareFieldOutput(string $field, array $data): string
    {
        $output = $field !== 'description' ? $this->snakeCaseToCapitalized($field) : (string)__('SEO description');

        if (isset($data[$field . '_TOOLBAR'])) {
            $marker = explode(' ', $data[$field . '_TOOLBAR'])[0];
            $marker = strtolower($marker);

            $className = $marker == 'template' ? 'active' : '';

            $output .= '<sup class="' . $className . '">' . $marker . '</sup>';
        }

        return $output;
    }

    public function getSeoTemplateId(): ?int
    {
        return $this->getRequest()->getParam(TemplateInterface::ID)
            ? (int)$this->getRequest()->getParam(TemplateInterface::ID)
            : null;
    }

    public function prepareValueOutput(string $value): string
    {
        $value = htmlspecialchars($value);
        $value = str_replace('#vb#', '<i class="_variable">', $value);
        $value = str_replace('#ve#', '</i>', $value);

        return $value;
    }

    private function snakeCaseToCapitalized(string $string): string
    {
        $parts = explode('_', $string);

        $parts = array_map(function ($part) {
            return ucfirst($part);
        }, $parts);

        return implode(' ', $parts);
    }
}
