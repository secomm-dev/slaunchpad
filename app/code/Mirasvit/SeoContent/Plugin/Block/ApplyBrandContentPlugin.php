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



namespace Mirasvit\SeoContent\Plugin\Block;

use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoContent\Service\ContentService;

class ApplyBrandContentPlugin
{
    private $contentService;

    private $storeManager;

    public function __construct(
        ContentService $contentService,
        StoreManagerInterface $storeManager
    ) {
        $this->contentService = $contentService;
        $this->storeManager   = $storeManager;
    }

    public function afterGetBrandPage($subject, $brandPage)
    {
        $content = $this->contentService->getCurrentContent();
        $storeId = (int)$this->storeManager->getStore()->getId();

        if ($content->getBrandDescription()) {
            $brandPageContent = $brandPage->getContent();

            if (
                isset($brandPageContent[$storeId])
                && array_key_exists('brand_description', $brandPageContent[$storeId])
            ) {
                $brandPageContent[$storeId]['brand_description'] = $content->getBrandDescription();
                $brandPage->setContent($brandPageContent);
            }
        }

        return $brandPage;
    }
}
