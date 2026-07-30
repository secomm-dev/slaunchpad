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

use Magento\Catalog\Api\Data\ProductInterface;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;
use Mirasvit\SeoContent\Api\Data\ContentInterface;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Service\Content\Modifier\ModifierInterface;

class ContentService
{
    private $isProcessed = false;

    private $templateService;

    private $rewriteService;

    private $stateService;

    private $content;

    private $templateEngineService;

    /**
     * @var ModifierInterface[]
     */
    private $modifierPool;

    public function __construct(
        TemplateService $templateService,
        RewriteService $rewriteService,
        StateServiceInterface $stateService,
        ContentInterface $content,
        TemplateEngineServiceInterface $templateEngineService,
        array $modifierPool
    ) {
        $this->templateService       = $templateService;
        $this->rewriteService        = $rewriteService;
        $this->stateService          = $stateService;
        $this->content               = $content;
        $this->templateEngineService = $templateEngineService;
        $this->modifierPool          = $modifierPool;
    }

    public function isProcessablePage(): bool
    {
        if ($this->stateService->isCategoryPage()
            || $this->stateService->isProductPage()
            || $this->stateService->isCmsPage()
            || $this->stateService->isBlogPage()
            || $this->stateService->isBrandPage()
            || $this->stateService->isLandingPage()
            || $this->rewriteService->getRewrite()
        ) {
            return true;
        }

        return false;
    }

    public function isHomePage(): bool
    {
        return $this->stateService->isHomePage();
    }

    public function putDefaultMeta(array $meta): void
    {
        foreach ($meta as $property => $value) {
            $this->content->setData($property, $this->escapeJS($value));
        }

        $this->isProcessed = false;
    }

    public function getCurrentContent(?int $ruleType = null, ?ProductInterface $product = null): ContentInterface
    {
        if ($this->isProcessed) {
            return $this->content;
        }

        $this->content = $this->processCurrentContent($ruleType, $product);

        $this->isProcessed = true;

        return $this->content->setData($this->escapeJS($this->content->getData()));
    }

    public function processCurrentContent(?int $ruleType = null, ?ProductInterface $product = null): ContentInterface
    {
        if (!$ruleType) {
            $ruleType = $this->getRuleType();
        }

        $template = $this->templateService->getTemplate(
            $ruleType,
            $this->stateService->getCategory(),
            ($product) ?: $this->stateService->getProduct(),
            $this->stateService->getFilters() ?: null,
            $this->stateService->getCmsPage(),
            $this->stateService->getBlogPage(),
            $this->stateService->getBrandPage()
        );

        $rewrite = $this->rewriteService->getRewrite();

        if (
            $template
            && $ruleType == TemplateInterface::RULE_TYPE_PAGE
            && $this->isHomePage()
            && !$template->getApplyForHomepage()
        ) {
            return $this->content;
        }

        if (
            $template
            && $ruleType == TemplateInterface::RULE_TYPE_BRAND
            && $this->stateService->isAllBrandsPage()
            && !$template->getApplyForAllBrandsPage()
        ) {
            return $this->content;
        }

        if (
            $template
            && $ruleType == TemplateInterface::RULE_TYPE_NAVIGATION
            && $this->stateService->isLandingPage()
            && !$template->getApplyForLandingPages()
        ) {
            $template = $this->templateService->getTemplate(
                TemplateInterface::RULE_TYPE_LANDING,
                $this->stateService->getCategory(),
                ($product) ?: $this->stateService->getProduct(),
                $this->stateService->getFilters() ?: null,
                $this->stateService->getCmsPage(),
                $this->stateService->getBlogPage(),
                $this->stateService->getBrandPage()
            );
        }

        return $this->processContent($template, $rewrite, $product);
    }

    public function processContent(
        ?TemplateInterface $template = null,
        ?RewriteInterface $rewrite = null,
        ?ProductInterface $product = null,
        ?string $forceApplyTo = null
    ): ContentInterface {
        if ($template) {
            $this->content->setData(ContentInterface::DESCRIPTION_POSITION, $template->getDescriptionPosition());
            $this->content->setData(ContentInterface::DESCRIPTION_TEMPLATE, $template->getDescriptionTemplate());
            $this->content->setData(ContentInterface::APPLIED_TEMPLATE_ID, $template->getId());
        }

        if ($rewrite) {
            $this->content->setData(ContentInterface::APPLIED_REWRITE_ID, $rewrite->getId());
            $this->content->setData(ContentInterface::DESCRIPTION_POSITION, $rewrite->getDescriptionPosition());
            $this->content->setData(ContentInterface::DESCRIPTION_TEMPLATE, $rewrite->getDescriptionTemplate());

            if ($rewrite->getMetaRobots() && $rewrite->getMetaRobots() != RewriteInterface::META_ROBOTS_DEFAULT) {
                $this->content->setData(ContentInterface::META_ROBOTS, $rewrite->getMetaRobots());
            }
        }

        $properties = [
            ContentInterface::TITLE,
            ContentInterface::META_TITLE,
            ContentInterface::META_KEYWORDS,
            ContentInterface::META_DESCRIPTION,
            ContentInterface::DESCRIPTION,
            ContentInterface::SHORT_DESCRIPTION,
            ContentInterface::FULL_DESCRIPTION,
            ContentInterface::CATEGORY_DESCRIPTION,
            ContentInterface::BRAND_DESCRIPTION,
        ];

        foreach ($properties as $property) {
            $rewriteValue = $rewrite ? $rewrite->getData($property) : false;

            if ($rewriteValue) {
                $this->content->setData($property, $rewriteValue);

                $this->content->setData(
                    $property . '_TOOLBAR',
                    "Rewrite #{$rewrite->getId()}"
                );

                continue;
            }

            $templateValue = $template ? $template->getData($property) : false;

            if ($templateValue) {
                $this->content->setData($property, $templateValue);

                $this->content->setData(
                    $property . '_TOOLBAR',
                    "Template #{$template->getId()}"
                );
            }
        }

        foreach ($properties as $property) {
            $this->content->setData($property, $this->templateEngineService->render(
                (string)$this->content->getData($property),
                ($product) ? ['product' => $product] : []
            ));
        }

        foreach ($this->modifierPool as $modifier) {
            $this->content = $modifier->modify($this->content, $forceApplyTo);
        }

        return $this->content;
    }

    /**
     * @param array $data
     *
     * @return array|string|null
     */
    public function escapeJS($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $data[$key] = preg_replace('#<script(.*?)>(.*?)</script>#is', '', (string)$value) ?? '';
            }

            return $data;
        } else {
            return preg_replace('#<script(.*?)>(.*?)</script>#is', '', (string)$data);
        }
    }

    private function getRuleType(): int
    {
        if ($this->stateService->isProductPage()) {
            return TemplateInterface::RULE_TYPE_PRODUCT;
        } elseif ($this->stateService->isNavigationPage()) {
            return TemplateInterface::RULE_TYPE_NAVIGATION;
        } elseif ($this->stateService->isLandingPage()) {
            return TemplateInterface::RULE_TYPE_LANDING;
        } elseif ($this->stateService->isCategoryPage()) {
            return TemplateInterface::RULE_TYPE_CATEGORY;
        } elseif ($this->stateService->isCmsPage()) {
            return TemplateInterface::RULE_TYPE_PAGE;
        } elseif ($this->stateService->isBlogPage()) {
            return TemplateInterface::RULE_TYPE_BLOG;
        } elseif ($this->stateService->isBrandPage()) {
            return TemplateInterface::RULE_TYPE_BRAND;
        }

        return 0;
    }
}
