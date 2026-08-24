<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Secomm\UiWidget\Api\TemplateResolverInterface;

/**
 * Renders an allowlisted Secomm UI component.
 */
class SecommUi extends Template implements BlockInterface
{
    /**
     * @param Template\Context $context Template context.
     * @param TemplateResolverInterface $templateResolver Safe template resolver.
     * @param array $data Block data.
     */
    public function __construct(
        Template\Context $context,
        private readonly TemplateResolverInterface $templateResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Resolve the registered component template before rendering.
     */
    protected function _toHtml(): string
    {
        $template = $this->templateResolver->resolve((string) $this->getData('component'));
        if ($template === null) {
            return '';
        }

        $this->setTemplate($template);

        return parent::_toHtml();
    }
}
