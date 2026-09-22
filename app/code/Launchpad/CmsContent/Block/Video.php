<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Block;

use Magento\Framework\View\Element\Template;

/**
 * REAL SPACES video embed (TASK-0NNZCW v4.5, SLP-213).
 *
 * Rendered through a {{block}} directive in the homepage PageBuilder content:
 * directives survive admin saves (unlike raw iframes — the PageBuilder
 * round-trip drops them), and the markup lives in this module's template so
 * content edits cannot break the embed.
 */
class Video extends Template
{
}
