<?php

declare(strict_types=1);

namespace Launchpad\Homepage\Block\Newsletter;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Homepage newsletter subscribe form (TASK-0NNZCW v4.3, SLP-213).
 *
 * Rendered through the {{block}} directive in the Page Builder content. Used
 * instead of the CMS static-block widget because the Hyva CMS JIT observer
 * pre-processes page content, which consumes the widget usage map and leaves
 * the static-block widget empty on the second (real) render pass.
 */
class Subscribe extends Template
{
    public function getFormActionUrl(): string
    {
        return $this->getUrl('newsletter/subscriber/new');
    }
}
