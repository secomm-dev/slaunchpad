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

namespace Mirasvit\SeoContent\Plugin\Frontend\Framework\App\Action;

use Magento\Catalog\Helper\Output as CatalogOutputHelper;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\FrontControllerInterface as Subject;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoContent\Api\Data\ContentInterface;
use Mirasvit\SeoContent\Service\ContentService;

class ApplyProductContentPlugin
{
    private $isShortDescriptionWasProcessed   = false;

    private $isOgShortDescriptionWasProcessed = false;

    private $isDescriptionWasProcessed        = false;

    private $contentService;

    private $stateService;

    private $filterProvider;

    private $catalogOutputHelper;

    public function __construct(
        ContentService        $contentService,
        StateServiceInterface $stateService,
        FilterProvider        $filterProvider,
        CatalogOutputHelper   $catalogOutputHelper
    ) {
        $this->contentService      = $contentService;
        $this->stateService        = $stateService;
        $this->filterProvider      = $filterProvider;
        $this->catalogOutputHelper = $catalogOutputHelper;
    }

    public function afterDispatch(Subject $subject, object $response): object
    {
        if ($subject instanceof Forward) {
            return $response;
        }

        $this->catalogOutputHelper->addHandler('productAttribute', $this);

        return $response;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param CatalogOutputHelper $outputHelper
     * @param string              $outputHtml
     * @param array               $params
     *
     * @return string
     */
    public function productAttribute($outputHelper, $outputHtml, $params)
    {
        if (!$this->stateService->isProductPage()) {
            return $outputHtml;
        }

        if (!in_array($params['attribute'], ['name', 'short_description', 'og:short_description', 'description'])) {
            return $outputHtml;
        }

        $product = $params['product'];

        if ($product->getId() != $this->stateService->getProduct()->getId()) {
            return $outputHtml;
        }

        $content = $this->contentService->getCurrentContent();

        switch ($params['attribute']) {
            case 'name':
                if ($content->getTitle()) {
                    $outputHtml = $content->getTitle();
                }
                break;
            case 'short_description':
                if ($content->getShortDescription() && !$this->isShortDescriptionWasProcessed) {
                    $this->isShortDescriptionWasProcessed = true; #prevent recursive call

                    $outputHtml = $this->catalogOutputHelper->productAttribute(
                        $product,
                        $content->getShortDescription(),
                        'short_description'
                    );
                } elseif ($content->getDescription()
                    && $content->getDescriptionPosition() == ContentInterface::DESCRIPTION_POSITION_UNDER_SHORT_DESCRIPTION) {
                    $outputHtml .= $this->filterProvider->getPageFilter()->filter($content->getDescription());
                }

                break;

            case 'og:short_description':
                if ($content->getShortDescription() && !$this->isOgShortDescriptionWasProcessed) {
                    $this->isOgShortDescriptionWasProcessed = true; #prevent recursive call

                    $outputHtml = $this->catalogOutputHelper->productAttribute(
                        $product,
                        $content->getShortDescription(),
                        'short_description'
                    );
                    $this->isShortDescriptionWasProcessed = false;
                }

                break;

            case 'description':
                if ($content->getFullDescription() && !$this->isDescriptionWasProcessed) {
                    $this->isDescriptionWasProcessed = true; #prevent recursive call

                    $outputHtml = $this->catalogOutputHelper->productAttribute(
                        $product,
                        $content->getFullDescription(),
                        'description'
                    );
                } elseif ($content->getDescription()
                    && $content->getDescriptionPosition() == ContentInterface::DESCRIPTION_POSITION_UNDER_FULL_DESCRIPTION) {
                    $outputHtml .= $this->filterProvider->getPageFilter()->filter($content->getDescription());
                }

                break;
        }

        return $outputHtml;
    }
}
