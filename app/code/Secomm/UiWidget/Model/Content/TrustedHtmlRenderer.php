<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Content;

use Magento\Cms\Model\Template\FilterProvider;

/**
 * Applies Magento's native CMS block filter to explicitly trusted Admin content.
 */
class TrustedHtmlRenderer
{
    public function __construct(private readonly FilterProvider $filterProvider)
    {
    }

    /**
     * Render trusted CMS HTML and directives using the current store context.
     *
     * @throws \Exception When the configured CMS filter cannot process the content.
     */
    public function render(string $content): string
    {
        return $this->filterProvider->getBlockFilter()->filter($content);
    }
}
