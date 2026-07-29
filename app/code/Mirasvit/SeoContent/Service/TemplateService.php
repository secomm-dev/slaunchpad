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

namespace Mirasvit\SeoContent\Service;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Service\StateService;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Api\Repository\TemplateRepositoryInterface;
use Magento\Framework\Registry;

class TemplateService
{
    private $templateRepository;

    private $storeManager;

    private $stateService;

    private $registry;

    public function __construct(
        TemplateRepositoryInterface $templateRepository,
        StoreManagerInterface       $storeManager,
        StateService                $stateService,
        Registry                    $registry
    ) {
        $this->templateRepository = $templateRepository;
        $this->storeManager       = $storeManager;
        $this->stateService       = $stateService;
        $this->registry           = $registry;
    }

    public function getTemplate(
        int $ruleType,
        ?CategoryInterface $category,
        ?ProductInterface $product,
        ?DataObject $filterData,
        ?PageInterface $page,
        $blog = null,
        $brand = null
    ): ?TemplateInterface {
        // Check if the "Stop further rules processing" configuration is set
        $collection = $this->templateRepository->getCollection();
        $collection->addFieldToFilter(TemplateInterface::IS_ACTIVE, true)
            ->addFieldToFilter(TemplateInterface::RULE_TYPE, $ruleType)
            ->addFieldToFilter(TemplateInterface::STOP_RULE_PROCESSING, 1)
            ->addStoreFilter($this->storeManager->getStore())
            ->setOrder(TemplateInterface::SORT_ORDER, 'desc');

        if ($collection->count() === 0) {
            $collection = $this->templateRepository->getCollection();
            $collection->addFieldToFilter(TemplateInterface::IS_ACTIVE, true)
                ->addFieldToFilter(TemplateInterface::RULE_TYPE, $ruleType)
                ->addStoreFilter($this->storeManager->getStore())
                ->setOrder(TemplateInterface::SORT_ORDER, 'desc');
        }

        foreach ($collection as $template) {
            $isFollowRules = true;

            if ($category && $ruleType === TemplateInterface::RULE_TYPE_CATEGORY) {
                $categoryData  = $category->getData();
                $isFollowRules = $template->getRule()->validate($category);
                $category->setData($categoryData);
                if (!$isFollowRules && $template->getApplyForChildCategories()) {
                    $parent = $category->getParentCategory();

                    $counter = 0;
                    while ($parent
                        && $parent->getParentId() > 0
                        && !$isFollowRules
                        && $counter < 5
                    ) {
                        $isFollowRules = $template->getRule()->validate($parent);
                        $parent        = $parent->getParentCategory();
                        $counter++;
                    }
                }
            }

            if ($product && $ruleType === TemplateInterface::RULE_TYPE_PRODUCT) {
                $isFollowRules = $isFollowRules && $template->getRule()->validate($product);
                if ($template->getApplyForChildCategories() && !$isFollowRules) {
                    $this->registry->register('apply_for_child_categories', true);
                    $isFollowRules = $template->getRule()->validate($product);
                    $this->registry->unregister('apply_for_child_categories');
                }
            }

            if ($filterData && $ruleType === TemplateInterface::RULE_TYPE_NAVIGATION) {
                $filterDataBackup = $filterData->getData();
                $isFollowRules    = $isFollowRules && $template->getRule()->validate($filterData);
                $filterData->setData($filterDataBackup);
                if (!$isFollowRules && $template->getApplyForChildCategories()) {
                    $parent = $filterData->getParentCategory();

                    $counter = 0;
                    while ($parent
                        && $parent->getParentId() > 0
                        && !$isFollowRules
                        && $counter < 5
                    ) {
                        $isFollowRules = $template->getRule()->validate($parent);
                        $parent        = $parent->getParentCategory();
                        $counter++;
                    }
                }
            }

            if ($page && $ruleType === TemplateInterface::RULE_TYPE_PAGE) {
                if ($this->stateService->isHomePage()) {
                    $isFollowRules = $isFollowRules && $template->getApplyForHomepage();
                } else {
                    $isFollowRules = $isFollowRules && $template->getRule()->validate($page);
                }
            }

            if ($blog && $ruleType === TemplateInterface::RULE_TYPE_BLOG) {
                $isFollowRules = $isFollowRules && $template->getRule()->validate($blog);
            }

            if ($brand && $ruleType === TemplateInterface::RULE_TYPE_BRAND) {
                if ($this->stateService->isAllBrandsPage()) {
                    $isFollowRules = $isFollowRules && $template->getApplyForAllBrandsPage();
                } else {
                    $isFollowRules = $isFollowRules && $template->getRule()->validate($brand);
                }
            }

            if ($ruleType === TemplateInterface::RULE_TYPE_LANDING) {
                $landingPage = $this->stateService->getLandingPage();

                if ($landingPage instanceof AbstractModel) {
                    $isFollowRules = $isFollowRules && $template->getRule()->validate($landingPage);
                } else {
                    $isFollowRules = false;
                }
            }

            if ($isFollowRules) {
                return $template;
            }
        }

        return null;
    }
}
