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

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Mirasvit\Seo\Api\Service\StateServiceInterface;

class BlogData extends AbstractData
{
    private $objectManager;

    private $stateService;

    private $moduleManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        StateServiceInterface  $stateService,
        ModuleManager          $moduleManager
    ) {
        $this->objectManager = $objectManager;
        $this->stateService  = $stateService;
        $this->moduleManager = $moduleManager;

        parent::__construct();
    }

    public function getTitle(): string
    {
        return (string)__('Blog Data');
    }

    public function getVariables(): array
    {
        if (!$this->moduleManager->isEnabled('Mirasvit_BlogMx')) {
            return [];
        }

        return [
            'name',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'post_author',
            'post_categories',
            'post_tags',
            'post_content',
            'author_job_title',
            'author_short_bio',
            'author_full_bio',
            'category_url',
            'category_parent_name',
            'category_parent_url',
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getValue(string $attribute, array $additionalData = []): ?string
    {
        $value = null;

        if ($blogPage = $this->stateService->getBlogPage()) {
            switch (substr($attribute, 0, (strpos($attribute, '_') ?: null))) {
                case 'post':
                    if ($blogPage instanceof \Mirasvit\BlogMx\Api\Data\PostInterface) {
                        $value = $this->getPostAttributeValue($blogPage, $attribute);
                    }

                    break;
                case 'author':
                    if ($blogPage instanceof \Mirasvit\BlogMx\Api\Data\AuthorInterface) {
                        $value = $this->getAuthorAttributeValue($blogPage, $attribute);
                    }

                    break;
                case 'category':
                    if ($blogPage instanceof \Mirasvit\BlogMx\Api\Data\CategoryInterface) {
                        $value = $this->getCategoryAttributeValue($blogPage, $attribute);
                    }

                    break;
                default:
                    $value = $blogPage->getDataUsingMethod($attribute) ?: null;

                    break;
            }
        }

        return $value;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getPostAttributeValue($post, string $attribute): ?string
    {
        $value = null;

        switch ($attribute) {
            case 'post_author':
                $authorRepository = $this->objectManager->create('Mirasvit\BlogMx\Repository\AuthorRepository');
                $author           = $authorRepository->get((int)$post->getData('author_id'));
                $value            = $author ? $author->getName() : null;

                break;
            case 'post_categories':
                $categoryIds        = $post->getCategoryIds();
                $categoryRepository = $this->objectManager->create('Mirasvit\BlogMx\Repository\CategoryRepository');
                $categoryNames      = [];

                foreach ($categoryIds as $id) {
                    if ($category = $categoryRepository->get((int)$id)) {
                        $categoryNames[] = $category->getName();
                    }
                }

                $value = !empty($categoryNames) ? implode(', ', $categoryNames) : null;

                break;
            case 'post_tags':
                $tagIds        = $post->getTagIds();
                $tagRepository = $this->objectManager->create('Mirasvit\BlogMx\Repository\TagRepository');
                $tagNames      = [];

                foreach ($tagIds as $id) {
                    if ($tag = $tagRepository->get((int)$id)) {
                        $tagNames[] = $tag->getName();
                    }
                }

                $value = !empty($tagNames) ? implode(', ', $tagNames) : null;

                break;
            case 'post_content':
                $value = $post->getContent();

                break;
        }

        return $value;
    }

    private function getAuthorAttributeValue($author, $attribute): ?string
    {
        $value = null;

        if ($attribute === 'author_job_title') {
            $value = $author->getJobTitle();
        } elseif ($attribute === 'author_short_bio') {
            $value = $author->getShortBio();
        } elseif ($attribute === 'author_full_bio') {
            $value = $author->getFullBio();
        }

        return $value;
    }

    private function getCategoryAttributeValue($category, $attribute): ?string
    {
        $value = null;

        switch ($attribute) {
            case 'category_url':
                $value = $category->getUrl();

                break;
            case 'category_parent_name':
                $categoryRepository = $this->objectManager->create('Mirasvit\BlogMx\Repository\CategoryRepository');
                $parentCategory     = $categoryRepository->get((int)$category->getParentId());
                $value              = $parentCategory ? $parentCategory->getName() : null;

                break;
            case 'category_parent_url':
                $categoryRepository = $this->objectManager->create('Mirasvit\BlogMx\Repository\CategoryRepository');
                $parentCategory     = $categoryRepository->get((int)$category->getParentId());
                $value              = $parentCategory ? $parentCategory->getUrl() : null;

                break;
        }

        return $value;
    }
}
