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

namespace Mirasvit\SeoMarkup\Block\Rs;

use Magento\Cms\Helper\Page as CmsHelper;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Header\Logo;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\SeoMarkup\Model\Config;
use Mirasvit\SeoMarkup\Model\Config\PageConfig;
use Mirasvit\SeoMarkup\Model\Config\Source\Page\SchemaType;

class Page extends Template
{
    private $config;

    private $stateService;

    private $logo;

    private $cmsPage;

    private $cmsHelper;

    private $serializer;

    public function __construct(
        PageConfig            $config,
        StateServiceInterface $stateService,
        Logo                  $logo,
        CmsPage               $cmsPage,
        CmsHelper             $cmsHelper,
        Context               $context,
        Json                  $serializer
    ) {
        $this->config       = $config;
        $this->stateService = $stateService;
        $this->logo         = $logo;
        $this->cmsPage      = $cmsPage;
        $this->cmsHelper    = $cmsHelper;
        $this->serializer   = $serializer;

        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    protected function _toHtml()
    {
        $storeId = (int)$this->_storeManager->getStore()->getId();

        if (!$this->config->isRsEnabled($storeId)) {
            return '';
        }

        $data = $this->getJsonData($storeId);

        if (!$data) {
            return '';
        }

        return '<script type="application/ld+json">' . $this->serializer->serialize($data) . '</script>';
    }

    private function getJsonData(int $storeId): array
    {
        $schemaType = $this->resolveSchemaType($storeId);

        switch ($schemaType) {
            case SchemaType::TYPE_ARTICLE:
            case SchemaType::TYPE_BLOG_POSTING:
                return $this->getArticleData($schemaType);

            default:
                return $this->getWebPageData($schemaType);
        }
    }

    private function resolveSchemaType(int $storeId): string
    {
        $pageSchemaType = $this->cmsPage->getData('mst_seo_markup_schema_type');

        if ($pageSchemaType) {
            return $pageSchemaType;
        }

        return $this->config->getDefaultSchemaType($storeId);
    }

    private function getWebPageData(string $schemaType): array
    {
        $typeMap = [
            SchemaType::TYPE_WEBPAGE      => 'WebPage',
            SchemaType::TYPE_ABOUT_PAGE   => 'AboutPage',
            SchemaType::TYPE_CONTACT_PAGE => 'ContactPage',
        ];

        $data = [
            '@context'      => Config::HTTP_SCHEMA_ORG,
            '@type'         => $typeMap[$schemaType] ?? 'WebPage',
            'name'          => $this->getName(),
            'description'   => $this->getDescription(),
            'url'           => $this->getPageUrl(),
            'datePublished' => $this->getDatePublished(),
            'dateModified'  => $this->getDateModified(),
            'image'         => $this->getImageUrl(),
            'inLanguage'    => $this->getLanguage(),
        ];

        return $this->filterEmptyValues($data);
    }

    private function getArticleData(string $schemaType): array
    {
        $typeMap = [
            SchemaType::TYPE_ARTICLE      => 'Article',
            SchemaType::TYPE_BLOG_POSTING => 'BlogPosting',
        ];

        $organizationName = $this->_storeManager->getStore()->getFrontendName();
        $logoUrl          = $this->getLogoUrl();

        $data = [
            '@context'      => Config::HTTP_SCHEMA_ORG,
            '@type'         => $typeMap[$schemaType] ?? 'Article',
            'headline'      => $this->getName(),
            'description'   => $this->getDescription(),
            'url'           => $this->getPageUrl(),
            'datePublished' => $this->getDatePublished(),
            'dateModified'  => $this->getDateModified(),
            'image'         => $this->getImageUrl(),
            'inLanguage'    => $this->getLanguage(),
            'author'        => [
                '@type' => 'Organization',
                'name'  => $organizationName,
            ],
            'publisher'     => [
                '@type' => 'Organization',
                'name'  => $organizationName,
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => $logoUrl,
                ],
            ],
        ];

        return $this->filterEmptyValues($data);
    }

    private function getName(): string
    {
        return (string)$this->pageConfig->getTitle()->get();
    }

    private function getDescription(): string
    {
        $description = (string)$this->pageConfig->getDescription();
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $description) ?? $description;
        $description = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $description) ?? $description;
        $description = strip_tags($description);
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/\s+/u', ' ', $description) ?? $description;

        return trim($description);
    }

    private function getPageUrl(): string
    {
        if ($this->stateService->isHomePage()) {
            return $this->_urlBuilder->getBaseUrl();
        }

        return (string)$this->cmsHelper->getPageUrl($this->cmsPage->getId());
    }

    private function getDatePublished(): string
    {
        $creationTime = $this->cmsPage->getCreationTime();

        if (!$creationTime) {
            return '';
        }

        return date('Y-m-d', strtotime($creationTime));
    }

    private function getDateModified(): string
    {
        $updateTime = $this->cmsPage->getUpdateTime();

        if (!$updateTime) {
            return '';
        }

        return date('Y-m-d', strtotime($updateTime));
    }

    private function getImageUrl(): string
    {
        $ogImage = $this->cmsPage->getOpenGraphImageUrl();

        if ($ogImage) {
            return $ogImage;
        }

        return $this->getLogoUrl();
    }

    private function getLogoUrl(): string
    {
        // fix since Magento_Theme v101.1.4
        if (class_exists('Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver') && !$this->logo->getData('logoPathResolver')) {
            $logoPathResolver = ObjectManager::getInstance()->get('Magento\Theme\ViewModel\Block\Html\Header\LogoPathResolver');
            $this->logo->setData('logoPathResolver', $logoPathResolver);
        }

        return (string)$this->logo->getLogoSrc();
    }

    private function getLanguage(): string
    {
        return (string)$this->_storeManager->getStore()->getConfig('general/locale/code');
    }

    private function filterEmptyValues(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }

            return $value !== '' && $value !== null;
        });
    }
}
