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

namespace Mirasvit\Seo\Service\Alternate;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\Seo\Api\Service\Alternate\StrategyInterface;
use Mirasvit\Seo\Api\Service\Alternate\UrlInterface;

class LandingPageStrategy implements StrategyInterface
{
    private $manager;

    private $url;

    private $context;

    public function __construct(
        Manager      $manager,
        UrlInterface $url,
        Context      $context
    ) {
        $this->manager = $manager;
        $this->url     = $url;
        $this->context = $context;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getStoreUrls(): array
    {
        if (!$this->manager->isEnabled('Mirasvit_LandingPage')) {
            return [];
        }

        $landingPageId = $this->context->getRequest()->getParam('landing');

        if (!$landingPageId) {
            return [];
        }

        /** @var \Mirasvit\LandingPage\Repository\PageRepository $landingPageRepository */
        $landingPageRepository = ObjectManager::getInstance()->get('\Mirasvit\LandingPage\Repository\PageRepository');

        $landing = $landingPageRepository->get((int)$landingPageId);

        if (!$landing) {
            return [];
        }

        $storeUrls = $this->url->getStoresCurrentUrl();

        if (!$storeUrls) {
            return [];
        }

        $pageStoreIds = $landing->getStoreIds();

        if (!is_array($pageStoreIds)) {
            $pageStoreIds = explode(',', $pageStoreIds);
        }

        if (in_array(0, $pageStoreIds)) {
            return $storeUrls;
        }

        foreach ($storeUrls as $storeId => $url) {
            if (!in_array($storeId, $pageStoreIds)) {
                unset($storeUrls[$storeId]);
            }
        }

        return count($storeUrls) > 1 ? $this->url->processStoreParamForDuplicates($storeUrls) : [];
    }

    public function getAlternateUrl(array $storeUrls, ?int $entityId = null, ?int $storeId = null): array
    {
        return [];
    }
}
