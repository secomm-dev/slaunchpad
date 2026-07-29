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

namespace Mirasvit\Seo\Service\TemplateEngine\Data;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Api\Service\StateServiceInterface;

class CategoryData extends AbstractData
{
    /**
     * @var CategoryInterface|null
     */
    private $category;

    private $registry;

    private $storeManager;

    private $stateService;

    public function __construct(
        Registry $registry,
        StoreManagerInterface $storeManager,
        StateServiceInterface $stateService
    ) {
        $this->registry     = $registry;
        $this->storeManager = $storeManager;
        $this->stateService = $stateService;

        parent::__construct();
    }

    public function getTitle(): string
    {
        return (string)__('Category Data');
    }

    public function getVariables(): array
    {
        return [
            'name',
            'url',
            'title_h1',
            'page_title',
            'parent_name',
            'parent_name_[level]',
            'parent_url',
        ];
    }

    public function setCategory(CategoryInterface $category): AbstractData
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?CategoryInterface
    {
        if (!$this->category) {
            return $this->registry->registry('current_category') ?: null;
        }

        return $this->category;
    }

    public function getValue(string $attribute, array $additionalData = []): ?string
    {
        /** @var \Magento\Catalog\Model\Category $category */
        $category = $this->getCategory();

        if (!$category) {
            return null;
        }

        $landingPage = $this->stateService->getLandingPage();

        switch ($attribute) {
            case 'name':
                if ($landingPage) {
                    return (string)$landingPage->getName();
                }

                return (string)$category->getName();

            case 'title_h1':
                if ($landingPage) {
                    return (string)($landingPage->getPageTitle() ?: $landingPage->getName());
                }

                return (string)$category->getData('title_h1') ?: $category->getName();

            case 'page_title':
                if ($landingPage) {
                    return (string)$landingPage->getMetaTitle();
                }

                return (string)$category->getMetaTitle();

            case 'parent_name':
            case 'parent_name_1':
                $parent = $this->getParentCategory($category, 1);

                return $parent ? (string)$parent->getName() : null;

            case 'parent_name_2':
                $parent = $this->getParentCategory($category, 2);

                return $parent ? (string)$parent->getName() : null;

            case 'parent_name_3':
                $parent = $this->getParentCategory($category, 3);

                return $parent ? (string)$parent->getName() : null;

            case 'parent_url':
                $parent = $this->getParentCategory($category);

                return $parent ? (string)$parent->getUrl() : null;
        }

        $data = $category->getDataUsingMethod($attribute);

        return $data ? (string)$data : null;
    }

    private function getParentCategory(CategoryInterface $category, int $level = 1): ?CategoryInterface
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore();

        /** @var \Magento\Catalog\Model\Category $parent */
        try {
            $parent = $category->getParentCategory();
        } catch (\Exception $e) {
            // intentional no-op: no resolvable parent for this template placeholder ⇒ treat as none.
            $parent = null;
        }

        if (!$parent) {
            return null;
        }

        if ($store->getRootCategoryId() == $parent->getId()) {
            return null;
        }

        if ($level <= 1) {
            return $parent;
        } else {
            return $this->getParentCategory($parent, $level - 1);
        }
    }
}
