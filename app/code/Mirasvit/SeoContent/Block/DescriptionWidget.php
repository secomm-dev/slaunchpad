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

namespace Mirasvit\SeoContent\Block;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use Mirasvit\SeoContent\Service\ContentService;

class DescriptionWidget extends Template implements BlockInterface
{
    protected $_template = 'Mirasvit_SeoContent::description.phtml';

    private   $contentService;

    private   $filterProvider;

    public function __construct(
        ContentService $contentService,
        FilterProvider $filterProvider,
        Context        $context,
        array          $data = []
    ) {
        $this->contentService = $contentService;
        $this->filterProvider = $filterProvider;

        parent::__construct($context, $data);
    }

    public function getDescription(): string
    {
        $content = $this->contentService->getCurrentContent();

        return $this->filterProvider->getPageFilter()->filter($content->getDescription());
    }
}
