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

use Magento\Cms\Helper\Page as CmsHelper;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Theme\Block\Html\Header\Logo;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoMarkup\Model\Config\PageConfig;

class Page extends AbstractBlock
{
    private $cmsPage;

    private $cmsHelper;

    private $config;

    private $stateService;

    private $logo;

    public function __construct(
        PageConfig            $pageConfig,
        StateServiceInterface $stateService,
        Logo                  $logo,
        Template\Context      $context,
        CmsPage               $cmsPage,
        CmsHelper             $cmsHelper
    ) {
        $this->config       = $pageConfig;
        $this->stateService = $stateService;
        $this->logo         = $logo;
        $this->cmsPage      = $cmsPage;
        $this->cmsHelper    = $cmsHelper;

        parent::__construct($context);
    }

    protected function getMeta(): ?array
    {
        $store = $this->_storeManager->getStore();

        if (!$this->config->isOgEnabled((int)$store->getId())) {
            return null;
        }

        if ($this->cmsPage->getOpenGraphImageUrl()) {
            $ogImage = $this->cmsPage->getOpenGraphImageUrl();
        } else {
            // fix since Magento_Theme v101.1.4
            if (class_exists('Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver') && !$this->logo->getData('logoPathResolver')) {
                $logoPathResolver = ObjectManager::getInstance()->get('Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver');
                $this->logo->setData('logoPathResolver', $logoPathResolver);
            }

            $ogImage = $this->logo->getLogoSrc();
        }

        $url = $this->stateService->isHomePage()
            ? $this->_urlBuilder->getBaseUrl()
            : $this->_urlBuilder->escape($this->getPageUrl((int)$this->cmsPage->getId()) ?? '');

        return [
            'og:type'        => $this->stateService->isHomePage() ? 'website' : 'article',
            'og:url'         => $url,
            'og:title'       => $this->pageConfig->getTitle()->get(),
            'og:description' => $this->pageConfig->getDescription(),
            'og:image'       => $ogImage,
            'og:site_name'   => $store->getFrontendName(),
        ];
    }

    private function getPageUrl(int $pageId): ?string
    {
        return $this->cmsHelper->getPageUrl($pageId) ?: null;
    }
}
