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

namespace Mirasvit\SeoContent\Plugin\Frontend\Framework\View\TemplateEngine;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\TemplateEngineInterface as Subject;
use Mirasvit\SeoContent\Api\Data\ContentInterface;
use Mirasvit\SeoContent\Service\ContentService;

/**
 * Purpose: Add Seo Description after specified template
 */
class AddSeoDescriptionPlugin
{
    private $contentService;

    private $filterProvider;

    /**
     * @var int
     */
    private $level = 0;

    /**
     * @var array
     */
    private $templates = [];

    public function __construct(
        ContentService $contentService,
        FilterProvider $filterProvider
    ) {
        $this->contentService = $contentService;
        $this->filterProvider = $filterProvider;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeRender(Subject $subject, ?BlockInterface $block = null, ?string $template = null)
    {
        $this->templates[$this->level] = $template;
        $this->level++;

        return null;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterRender(Subject $subject, string $result): string
    {
        $this->level--;
        $template = $this->templates[$this->level];

        $content = $this->contentService->getCurrentContent();

        if ($content->getDescriptionPosition() == ContentInterface::DESCRIPTION_POSITION_CUSTOM_TEMPLATE
            && !empty($content->getDescriptionTemplate())
            && strpos($template, $content->getDescriptionTemplate()) !== false) {
            $result .= $this->filterProvider->getPageFilter()->filter($content->getDescription());
        }

        return $result;
    }
}
