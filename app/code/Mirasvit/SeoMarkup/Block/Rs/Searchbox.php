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

use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\SeoMarkup\Model\Config\AeoConfig;
use Mirasvit\SeoMarkup\Model\Config\SearchboxConfig;

class Searchbox extends Template
{
    private $searchboxConfig;

    private $aeoConfig;

    private $serializer;

    public function __construct(
        Context         $context,
        SearchboxConfig $searchboxConfig,
        AeoConfig       $aeoConfig,
        Json            $serializer
    ) {
        $this->searchboxConfig = $searchboxConfig;
        $this->aeoConfig       = $aeoConfig;
        $this->serializer      = $serializer;

        parent::__construct($context);
    }

    /**
     * @return string|false
     */
    protected function _toHtml()
    {
        if (!$this->searchboxConfig->getSearchBoxType((int)$this->_storeManager->getStore()->getId())) {
            return false;
        }

        $data = $this->getJsonData();

        return '<script type="application/ld+json">' . $this->serializer->serialize($data) . '</script>';
    }

    private function getJsonData(): array
    {
        $data = [
            "@context"        => "https://schema.org",
            "@type"           => "WebSite",
            "name"            => $this->_storeManager->getStore()->getName(),
            "url"             => $this->getBaseUrl(),
            "potentialAction" => [
                "@type"       => "SearchAction",
                "target"      => $this->getTarget(),
                "query-input" => "required name=search_term_string",
            ],
        ];

        if ($this->aeoConfig->isAeoEnabled((int)$this->_storeManager->getStore()->getId())) {
            $data = ['@id' => $this->getBaseUrl() . AeoConfig::ID_WEBSITE] + $data;
        }

        return $data;
    }

    private function getTarget(): string
    {
        $searchboxType = $this->searchboxConfig->getSearchBoxType((int)$this->_storeManager->getStore()->getId());

        switch ($searchboxType) {
            case (SearchboxConfig::SEARCH_BOX_TYPE_CATALOG_SEARCH):
                $target = $this->getBaseUrl() . 'catalogsearch/result?q={search_term_string}';
                break;

            case (SearchboxConfig::SEARCH_BOX_TYPE_BLOG_SEARCH):
                $target = $this->getBaseUrl() . $this->searchboxConfig->getBlogSearchUrl() . '{search_term_string}';
                break;
        }

        return $target ?? '';
    }
}
