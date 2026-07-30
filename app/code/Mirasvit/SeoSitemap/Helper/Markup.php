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



namespace Mirasvit\SeoSitemap\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sitemap\Model\Sitemap;
use Mirasvit\SeoSitemap\Model\Config;

class Markup extends AbstractHelper
{
    private $config;

    public function __construct(
        Context $context,
        Config  $config
    ) {
        $this->config = $config;

        parent::__construct($context);
    }

    public function getTagsData(): array
    {
        return [Sitemap::TYPE_INDEX => [
                Sitemap::OPEN_TAG_KEY  => '<?xml version="1.0" encoding="UTF-8"?>' .
                    PHP_EOL .
                    '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
                    PHP_EOL,
                Sitemap::CLOSE_TAG_KEY => '</sitemapindex>',
            ],
            Sitemap::TYPE_URL   => [
                Sitemap::OPEN_TAG_KEY  => '<?xml version="1.0" encoding="UTF-8"?>' .
                    PHP_EOL .
                    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' .
                    ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' .
                    ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"' .
                    ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' .
                    PHP_EOL,
                Sitemap::CLOSE_TAG_KEY => '</urlset>',
            ],
        ];
    }

    /**
     * @param string $url
     * @param string $title
     * @param string $caption
     * @return string
     */
    public function getImageMarkup($url, $title, $caption)
    {
        if (!$caption) {
            $caption = $title;
        }

        $imageMarkup = '<image:image>';
        $imageMarkup .= '<image:loc>' . $url .'</image:loc>';
        if (!$this->config->removeOptionalImageTags()) {
            $imageMarkup .= '<image:title>' . $title .'</image:title>';
            $imageMarkup .= '<image:caption>' . $caption . '</image:caption>';
        }
        $imageMarkup .= '</image:image>';

        return $imageMarkup;
    }

    /**
     * @param string $title
     * @param string $url
     * @param string $alt
     * @return string
     */
    public function afterGetImageMarkup($title, $url, $alt)
    {
        if ($this->config->removePagemapTags()) {
            return '';
        }

        return '<PageMap xmlns="http://www.google.com/schemas/sitemap-pagemap/1.0"><DataObject type="thumbnail">'
            . '<Attribute name="name" value="'. $title .'"/>'
            . '<Attribute name="src" value="'. $url .'"/>'
            . '<Attribute name="alt" value="'. $alt .'"/>'
            . '</DataObject></PageMap>';
    }

    public function getAlternateLinkMarkup($hreflang, $href)
    {
        return '<xhtml:link rel="alternate" hreflang="' . $hreflang . '" href="' . $href . '"/>';
    }

    /**
     * @param string $thumbnailUrl
     * @param string $title
     * @param string $description
     * @param string $playerUrl
     * @return string
     */
    public function getVideoMarkup($thumbnailUrl, $title, $description, $playerUrl)
    {
        $videoMarkup = '<video:video>';
        $videoMarkup .= '<video:thumbnail_loc>' . $thumbnailUrl . '</video:thumbnail_loc>';
        $videoMarkup .= '<video:title>' . $title . '</video:title>';
        $videoMarkup .= '<video:description>' . $description . '</video:description>';
        $videoMarkup .= '<video:player_loc>' . $playerUrl . '</video:player_loc>';
        $videoMarkup .= '</video:video>';

        return $videoMarkup;
    }
}
