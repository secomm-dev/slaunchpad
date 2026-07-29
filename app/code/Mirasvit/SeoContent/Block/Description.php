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
use Mirasvit\SeoContent\Api\Data\ContentInterface;
use Mirasvit\SeoContent\Service\ContentService;

class Description extends Template
{
    private $contentService;

    private $filterProvider;

    /**
     * @var mixed|string|null
     */
    private $position;

    public function __construct(
        ContentService $contentService,
        FilterProvider $filterProvider,
        Context        $context,
        array          $data = []
    ) {
        $this->contentService = $contentService;
        $this->filterProvider = $filterProvider;
        $this->position       = $data['position'] ?? '';

        parent::__construct($context, $data);
    }

    /**
     * @return bool|string
     */
    public function getDescription()
    {
        $content             = $this->contentService->getCurrentContent();
        $descriptionPosition = $content->getDescriptionPosition();

        if (
            $this->position === 'bottom'
            && $descriptionPosition === ContentInterface::DESCRIPTION_POSITION_BOTTOM_PAGE
        ) {
            return $this->filterProvider->getPageFilter()->filter($content->getDescription());
        }

        if (
            $this->position === 'content'
            && $descriptionPosition === ContentInterface::DESCRIPTION_POSITION_UNDER_PRODUCT_LIST
        ) {
            return $this->filterProvider->getPageFilter()->filter($content->getDescription());
        }

        return false;
    }
}
