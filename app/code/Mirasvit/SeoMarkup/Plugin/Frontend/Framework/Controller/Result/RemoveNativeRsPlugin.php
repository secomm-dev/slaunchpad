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

namespace Mirasvit\SeoMarkup\Plugin\Frontend\Framework\Controller\Result;

use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoMarkup\Model\Config\LandingConfig;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;
use Mirasvit\SeoMarkup\Model\Config\CategoryConfig;
use Mirasvit\SeoMarkup\Model\Config\PageConfig;

class RemoveNativeRsPlugin
{
    private $stateService;

    private $productConfig;

    private $categoryConfig;

    private $landingConfig;

    private $pageConfig;

    private $response;

    private $storeManager;

    public function __construct(
        StateServiceInterface $stateService,
        ProductConfig         $productConfig,
        CategoryConfig        $categoryConfig,
        LandingConfig         $landingConfig,
        PageConfig            $pageConfig,
        ResponseInterface     $response,
        StoreManagerInterface $storeManager
    ) {
        $this->stateService   = $stateService;
        $this->productConfig  = $productConfig;
        $this->categoryConfig = $categoryConfig;
        $this->landingConfig  = $landingConfig;
        $this->pageConfig     = $pageConfig;
        $this->response       = $response;
        $this->storeManager   = $storeManager;
    }

    /**
     * @param mixed $subject
     * @param mixed $result
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterRenderResult($subject, $result)
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        if (
            ($this->stateService->isProductPage() && $this->productConfig->isRemoveNativeRs($storeId))
            || ($this->stateService->isCategoryPage() && $this->categoryConfig->isRemoveNativeRs($storeId))
            || ($this->stateService->isLandingPage() && $this->landingConfig->isRemoveNativeRs($storeId))
            || ($this->stateService->isCmsPage() && $this->pageConfig->isRemoveNativeRs($storeId))
        ) {
            $body = $this->response->getBody();
            $body = $this->deleteWrongSnippets($body);
            $this->response->setBody($body);
        }

        return $result;
    }

    /**
     * Remove itemprop, itemscope from breadcrumbs html
     * @return array|string|null
     */
    public function deleteWrongSnippets(string $html)
    {
        $crumbsPattern = '/\\<span class="breadcrumbsbefore"\\>\\<\\/span\\>(.*?)'
            . '\\<span class="breadcrumbsafter"\\>\\<\\/span\\>/ims';
        preg_match($crumbsPattern, $html, $crumbs);

        $pattern = [
            '/itemprop="(.*?)"/ims',
            '/itemprop=\'(.*?)\'/ims',
            '/itemtype="(.*?)"/ims',
            '/itemtype=\'(.*?)\'/ims',
            '/itemscope="(.*?)"/ims',
            '/itemscope=\'(.*?)\'/ims',
            '/itemscope=\'\'/ims',
            '/itemscope=""/ims',
            '/itemscope\s/ims',
        ];

        $html = preg_replace($pattern, '', $html) ?? '';

        if (isset($crumbs[1]) && $crumbs[1]) {
            $html = preg_replace($crumbsPattern, $crumbs[1], $html) ?? $html;
        }

        return $html;
    }
}
