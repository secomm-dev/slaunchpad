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

namespace Mirasvit\SeoMarkup\Block\Og;

use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Header\Logo;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoMarkup\Model\Config\CategoryConfig;

class Category extends AbstractBlock
{
    private $categoryConfig;

    private $stateService;

    private $logo;

    public function __construct(
        CategoryConfig        $categoryConfig,
        StateServiceInterface $stateService,
        Logo                  $logo,
        Context               $context
    ) {
        $this->categoryConfig = $categoryConfig;
        $this->stateService   = $stateService;
        $this->logo           = $logo;

        parent::__construct($context);
    }

    protected function getMeta(): ?array
    {
        $store = $this->_storeManager->getStore();

        if (!$this->categoryConfig->isOgEnabled((int)$store->getId()) || $this->stateService->isLandingPage()) {
            return null;
        }

        $category = $this->stateService->getCategory();

        if (!$category) {
            return null;
        }

        $imageUrl  = $this->logo->getLogoSrc();
        $catImgUrl = $category->getImageUrl();

        $imageConfig = $this->categoryConfig->getImage();

        if ($catImgUrl && $imageConfig
            && ($imageConfig == CategoryConfig::IMAGE_YES || !$this->stateService->isNavigationPage())
        ) {
            if (class_exists('\Magento\Catalog\Model\Category\Image')) {
                $categoryImage = \Magento\Framework\App\ObjectManager::getInstance()
                    ->get('\Magento\Catalog\Model\Category\Image');

                /** @var \Magento\Catalog\Model\Category\Image $categoryImage */
                $imageUrl = $categoryImage->getUrl($category, 'image');
            } else {
                $imageUrl = $catImgUrl;
            }
        }

        return [
            'og:type'        => 'product.group',
            'og:url'         => $this->_urlBuilder->escape($category->getUrl()),
            'og:title'       => $this->pageConfig->getTitle()->get(),
            'og:description' => $this->pageConfig->getDescription(),
            'og:image'       => $imageUrl,
            'og:site_name'   => $store->getFrontendName(),
        ];
    }
}
