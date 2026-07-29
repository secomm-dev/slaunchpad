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

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Header\Logo;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoMarkup\Model\Config\LandingConfig;

class Landing extends AbstractBlock
{
    private $landingConfig;

    private $stateService;

    private $logo;

    private $urlFactory;

    private $objectManager;

    public function __construct(
        LandingConfig          $landingConfig,
        StateServiceInterface  $stateService,
        Logo                   $logo,
        UrlFactory             $urlFactory,
        ObjectManagerInterface $objectManager,
        Context                $context
    ) {
        $this->landingConfig = $landingConfig;
        $this->stateService  = $stateService;
        $this->logo          = $logo;
        $this->urlFactory    = $urlFactory;
        $this->objectManager = $objectManager;

        parent::__construct($context);
    }

    protected function getMeta(): ?array
    {
        $store = $this->_storeManager->getStore();

        if (!$this->landingConfig->isOgEnabled((int)$store->getId())) {
            return null;
        }

        $page = $this->stateService->getLandingPage();

        if (!$page) {
            return null;
        }

        $imageUrl   = $this->logo->getLogoSrc();
        $pageImgUrl = null;

        if ($page->getImage()) {
            $pageImageUrlService = $this->objectManager->create('Mirasvit\LandingPage\Service\ImageUrlService');

            $pageImgUrl = $pageImageUrlService->getImageUrl($page->getImage());
        }

        $imageConfig = $this->landingConfig->getImage();

        if ($pageImgUrl && $imageConfig
            && ($imageConfig == LandingConfig::IMAGE_YES || !$this->stateService->isNavigationPage())
        ) {
            $imageUrl = $pageImgUrl;
        }

        $url  = $this->urlFactory->create()->getUrl($page->getUrlKey());

        return [
            'og:type'        => 'product.group',
            'og:url'         => $this->_urlBuilder->escape($url),
            'og:title'       => $this->pageConfig->getTitle()->get(),
            'og:description' => $this->pageConfig->getDescription(),
            'og:image'       => $imageUrl,
            'og:site_name'   => $store->getFrontendName(),
        ];
    }
}
