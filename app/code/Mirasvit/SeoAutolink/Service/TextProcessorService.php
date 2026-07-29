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

namespace Mirasvit\SeoAutolink\Service;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoAutolink\Helper\Pattern;
use Mirasvit\SeoAutolink\Model\Config;
use Mirasvit\SeoAutolink\Model\Config\Source\Occurence;
use Mirasvit\SeoAutolink\Model\Link;
use Mirasvit\SeoAutolink\Model\LinkFactory;
use Mirasvit\SeoAutolink\Model\ResourceModel\Link\Collection as LinksCollection;
use Mirasvit\SeoAutolink\Api\Data\LinkInterface;
use Mirasvit\SeoAutolink\Service\TextProcessor\Strings;
use Mirasvit\SeoAutolink\Service\TextProcessor\TextPlaceholder;

class TextProcessorService
{
    const MAX_NUMBER = 999999;

    private $linkFactory;

    private $config;

    private $seoAutolinkPattern;

    private $context;

    private $storeManager;

    private $registry;

    private $entityUrlResolver;

    private $cache = [];

    protected $_isSkipLinks;

    protected $_sizeExplode = 0;

    protected $_isExcludedTags = true;

    protected $_replacementsCountGlobal = [];

    protected $currentNumberOfLinks = 0;

    private $processedLinks = [];

    public function __construct(
        LinkFactory $linkFactory,
        Config $config,
        Pattern $seoAutolinkPattern,
        Context $context,
        StoreManagerInterface $storeManager,
        Registry $registry,
        EntityUrlResolver $entityUrlResolver
    ) {
        $this->linkFactory        = $linkFactory;
        $this->config             = $config;
        $this->seoAutolinkPattern = $seoAutolinkPattern;
        $this->context            = $context;
        $this->storeManager       = $storeManager;
        $this->registry           = $registry;
        $this->entityUrlResolver  = $entityUrlResolver;
    }

    /**
     * Main entry point. Inserts links into text.
     */
    public function addLinks(?string $text = null): ?string
    {
        if (!$text) {
            return $text;
        }

        if (isset($this->cache[$text])) {
            return $this->cache[$text];
        }

        if (strpos($this->context->getUrlBuilder()->getCurrentUrl(), '/checkout/onepage/') !== false
            || strpos($this->context->getUrlBuilder()->getCurrentUrl(), 'onestepcheckout') !== false) {
            return $text;
        }

        if ($this->checkSkipLinks() === true) {
            return $text;
        }

        $processed = Strings::replaceSpecialCharacters($text);

        $links              = $this->getLinks($processed);
        $patternsForExclude = $this->getExcludedAutoTags();
        $processed          = $this->_addLinks($processed, $links, $patternsForExclude);

        $this->cache[$text] = $processed;

        return $processed;
    }

    protected function getStoreId(): int
    {
        return (!$this->storeManager->getStore()) ? 1 : (int)($this->storeManager->getStore()->getId());
    }

    /**
     * Returns value of setting "Links limit per page"
     */
    public function getMaxLinkPerPage(): int
    {
        if ($max = (int)$this->config->getLinksLimitPerPage($this->getStoreId())) {
            return $max;
        }

        return self::MAX_NUMBER;
    }

    /**
     * Returns collection of links with keywords which present in our text.
     * Not ALL possible links.
     * try get links with newer query, if returns SQLERROR
     * (for older Magento like 1.4 and specific MySQL configurations) -
     * get links with older query for backward compatibility
     */
    public function getLinks(string $text): LinksCollection
    {
        $textArrayWithMaxSymbols = Strings::splitText($text);

        if (count($textArrayWithMaxSymbols) > 5) {
            $links = $this->getLinksCollection();
            $links->getSelect()->order(['sort_order ASC', 'LENGTH(main_table.keyword) desc']);

            return $links;
        }

        $where = [];
        foreach ($textArrayWithMaxSymbols as $splitTextVal) {
            $where[] = "lower('" . addslashes($splitTextVal) . "') LIKE CONCAT(" . "'%'" . ', lower(keyword), ' . "'%'" . ')';
        }

        $links = $this->getLinksCollection();
        $links->getSelect()->where(implode(' OR ', $where))
            ->order(['sort_order ASC', 'LENGTH(main_table.keyword) desc']);

        try {
            $links->load(); //need to load collection to catch SQLERROR if occured
        } catch (\Exception $e) {
            $links = $this->getLinksCollection();
            $links->getSelect()->where("lower(?) LIKE CONCAT('%', lower(keyword), '%')", $text)
                ->order(['LENGTH(main_table.keyword) desc']); //we need to replace long keywords firstly
        }

        return $links;
    }

    /**
     * Prepare collection acceptable for both variants of SQL queries.
     */
    private function getLinksCollection(): LinksCollection
    {
        $links = $this->linkFactory->create()->getCollection();
        /** @var LinksCollection $links */
        $links
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore());

        return $links;
    }


    /**
     * Inserts links into text
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function _addLinks(
        string $text,
        LinksCollection $links,
        array $excludedTags,
        bool $replacementCountForTests = false
    ): string {
        try {
            if (count($links) == 0) {
                return $text;
            }
        } catch (\Exception $e) {
            $this->context->getLogger()->info($e->getMessage());
            return $text;
        }

        $pregPatterns       = $this->getPatterns();
        $patternsForExclude = $this->convertTagsToPatterns($excludedTags);
        $pregPatterns       = array_merge($patternsForExclude, $pregPatterns);
        $placeholder        = new TextPlaceholder($text, $pregPatterns);
        $text               = $placeholder->getTokenizedText();

        foreach ($links as $link) {
            if ($link->getLinkType() !== LinkInterface::LINK_TYPE_URL) {
                $entityId = $link->getLinkEntityId();
                if (!$entityId) {
                    continue;
                }
                if ($link->getLinkType() === LinkInterface::LINK_TYPE_PRODUCT) {
                    $linkUrl = $this->entityUrlResolver->resolveProductUrl($entityId);
                } else {
                    $linkUrl = $this->entityUrlResolver->resolveCategoryUrl($entityId);
                }
                if (!$linkUrl) {
                    continue;
                }
            } else {
                $linkUrl = $this->_prepareLinkUrl($link->getUrl() ?? '');
            }

            if ($this->context->getUrlBuilder()->getCurrentUrl() === $linkUrl) {
                continue;
            }

            if (strlen($link->getKeyword()) <= 1) { //one letter can't be in autolinks
                continue;
            }

            if ($this->config->getStopKeywordProcessing()) {
                $linkHash = hash('sha256', $linkUrl);
                if (isset($this->processedLinks[$linkHash]) && $this->processedLinks[$linkHash] != $link->getId()) {
                    continue;
                }
                $this->processedLinks[$linkHash] = $link->getId();
            }

            /** @var Link $link */
            $replaceKeyword = $link->getKeyword();
            $urltitle       = $link->getUrlTitle() ? "title='{$link->getUrlTitle()}' " : '';
            $nofollow       = $link->getIsNofollow() ? 'rel=\'nofollow\' ' : '';
            $target         = $link->getUrlTarget() ? "target='{$link->getUrlTarget()}' " : '';

            $replaceLimit = '';
            $limitPerPage = '';

            $html = "<a class='mst_seo_autolink autolink' href='{$linkUrl}'"
                . " {$urltitle}{$target}{$nofollow}{$limitPerPage}{$replaceLimit}>"
                . $link->getKeyword() . "</a>";

            $maxReplacements = self::MAX_NUMBER;
            if ($link->getMaxReplacements() > 0) {
                $maxReplacements = (int)$link->getMaxReplacements();
            }
            if ($replacementCountForTests) { //for tests
                $maxReplacements = (int)$replacementCountForTests;
            }

            $direction = 0;
            switch ($link->getOccurence()) {
                case Occurence::FIRST:
                    $direction = 0;
                    break;
                case Occurence::LAST:
                    $direction = 1;
                    break;
                case Occurence::RANDOM:
                    $direction = rand(0, 1);
                    break;
            }

            $text = $this->replace($html, $text, $maxReplacements, $replaceKeyword, $direction);
        }

        $translationTable = $placeholder->getTranslationTableArray();

        $text = $this->_restoreSourceByTranslationTable($translationTable, $text);

        return $text;
    }

    protected function convertTagsToPatterns(array $excludedTags): array
    {
        $patternsForExclude = [];
        foreach ($excludedTags as $tag) {
            $tag                  = str_replace(' ', '', $tag);
            $patternsForExclude[] = '#' . '<' . $tag . '[^>]*>[\s\S]*</' . $tag . '>' . '#iU';
        }

        return $patternsForExclude;
    }

    /**
     * Returns link url with base url (need to get correct store code in url)
     */
    protected function _prepareLinkUrl(string $url): string
    {
        if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            if (substr($url, 0, 1) == '/') {
                $url = substr($url, 1);
            }
            $url = $baseUrl . $url;
        }

        return $url;
    }

    /**
     * Returns array of patterns, which will be used to find and replace keywords
     */
    protected function getPatterns(): array
    {
        // matches for these expressions will be replaced with a unique placeholder
        $pregPatterns = [
            '#<!--.*?-->#s'       // html comments
            , '#<a [^>]*>.*?<\/a>#iU' // html links
            , '#<a(.+)((\s)+(.+))+\/a>#iU' // html links
        ];

        return $pregPatterns;
    }


    /**
     * Reconstruct the original text
     */
    protected function _restoreSourceByTranslationTable(array $translationTable, string $source): string
    {
        foreach ($translationTable as $key => $value) {
            foreach ($translationTable as $key2 => $value2) {
                $value = str_replace($key2, $value2, $value);
            }

            $source = str_replace($key, $value, $source);
        }

        return $source;
    }

    /**
     * Replace words and left the same cases
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function replace(
        string  $replace,
        string  $source,
        int     $maxReplacements,
        ?string $replaceKeyword = null,
        ?int    $direct = null
    ): string {
        if ($this->currentNumberOfLinks >= $this->getMaxLinkPerPage()) { //Links limit per page
            return $source;
        }

        if ($maxReplacements > 0 && $this->getReplacementCount($replaceKeyword ?? '') > $maxReplacements) {
            return $source;
        }

        $maxReplacements -= $this->getReplacementCount($replaceKeyword ?? '');
        $pattern         = '/' . preg_quote((string)$replaceKeyword, '/') . '/i';

        preg_match_all(
            $pattern,
            $source,
            $replaceKeywordVariations,
            PREG_OFFSET_CAPTURE
        );

        if (isset($replaceKeywordVariations[0])) {
            $keywordVariations = $replaceKeywordVariations[0];
            if (!empty($keywordVariations)) {
                if ($direct == 1) {
                    $keywordVariations = array_slice($keywordVariations, -$maxReplacements);
                } else {
                    $keywordVariations = array_slice($keywordVariations, 0, $maxReplacements);
                }
                foreach ($keywordVariations as $keywordValue) {
                    if ($this->currentNumberOfLinks >= $this->getMaxLinkPerPage()) { //Links limit per page
                        break;
                    }

                    $replaceForVariation = preg_replace(
                        '/(\\<a.*?\\>)(.*?)(\\<\\/a\\>)/',
                        $this->prepareReplacement($keywordValue[0]),
                        $replace
                    ) ?? $replace;

                    $source              = $this->addLinksToSource(
                        $maxReplacements,
                        $direct ?? 0,
                        $source,
                        $keywordValue[0],
                        $replaceForVariation
                    );
                }
                $this->_sizeExplode = 0;
            }
        }

        return $source;
    }

    public function prepareReplacement(string $keyword): string
    {
        if (is_numeric(Strings::substr($keyword))) {
            $replacement = "$1 $keyword $3";
        } else {
            $replacement = '$1' . $keyword . '$3';
        }

        return $replacement;
    }

    public function addLinksToSource(
        int $maxReplacements,
        int $direct,
        string $source,
        string $replaceKeyword,
        string $replace
    ): string {
        $originalReplaceKeyword = $replaceKeyword;
        if ($this->currentNumberOfLinks > $this->getMaxLinkPerPage()) {
            return $source;
        }

        $replacementPlaceholder = sha1($replace);

        // replace previously replaced with hash placeholder
        $source = preg_replace('#' . preg_quote($replace, '#') . '#', $replacementPlaceholder, $source) ?? $source;

        if ($direct == 1) {
            $source         = strrev($source);
            $replaceKeyword = strrev($replaceKeyword);
            $replace        = strrev($replace);
        }
        if ($replaceKeyword === '') {
            return $source;
        }
        $explodeSource        = explode($replaceKeyword, $source); // explode text
        $nextSymbol           = ['', ' ', chr(160), ',', '.', '!', '?', ')', "\n", "\r", "\r\n"]; // symbols after the word
        $prevSymbol           = [',', ' ', chr(160), '(', "\n", "\r", "\r\n"]; // symbols before the word
        $nextTextPatternArray = ['(.*?)&nbsp;$', '(.*?)&lt;span&gt;$'];    // text pattern after the word
        $prevTextPatternArray = ['^&nbsp;(.*?)', '^&lt;\/span&gt;(.*?)']; // text pattern before the word
        $nextPattern          = '/' . implode('|', $nextTextPatternArray) . '/';
        $prevPattern          = '/' . implode('|', $prevTextPatternArray) . '/';

        $sizeExplodeSource = count($explodeSource);
        $size              = 0;
        $prepareSource     = '';

        $replaceNumberOne = false;

        $numberOfReplacements = 0;
        $isStopReplacement    = false;

        foreach ($explodeSource as $keySource => $valSource) {
            $size++;
            $replaceIsDone = false;
            if (!$isStopReplacement &&
                $size < $sizeExplodeSource &&
                $this->_sizeExplode < $maxReplacements
                && !$replaceNumberOne) {
                $lastSymbolBeforeReplacement = false;
                if (!empty($valSource[strlen($valSource) - 1])) {
                    $lastSymbolBeforeReplacement = $valSource[strlen($valSource) - 1];
                }

                $nextSymbolAfterReplacement = false;
                if (!empty($explodeSource[$keySource + 1][0])) {
                    $nextSymbolAfterReplacement = $explodeSource[$keySource + 1][0];
                }

                if ($direct == 0) {
                    $isBeforeReplacementAllowed
                        = $lastSymbolBeforeReplacement === false
                        || $lastSymbolBeforeReplacement === " "
                        || in_array($lastSymbolBeforeReplacement, $prevSymbol)
                        || preg_match($nextPattern, $valSource);

                    $isAfterReplacementAllowed
                        = $nextSymbolAfterReplacement === false
                        || $nextSymbolAfterReplacement === " "
                        || in_array($nextSymbolAfterReplacement, $nextSymbol)
                        || preg_match($nextPattern, $valSource);

                    // maxReplacements for written letters
                    if ($isBeforeReplacementAllowed && $isAfterReplacementAllowed) {
                        $prepareSource .= $valSource . $replace;
                        $replaceIsDone = true;
                    }
                } else {
                    $isBeforeReplacementAllowed
                        = $lastSymbolBeforeReplacement === false
                        || $lastSymbolBeforeReplacement === " "
                        || in_array($lastSymbolBeforeReplacement, $nextSymbol)
                        || preg_match($prevPattern, $valSource);

                    $isAfterReplacementAllowed
                        = $nextSymbolAfterReplacement === false
                        || $nextSymbolAfterReplacement === " "
                        || in_array($nextSymbolAfterReplacement, $prevSymbol)
                        || preg_match($nextPattern, $valSource);

                    if ($isBeforeReplacementAllowed && $isAfterReplacementAllowed) {
                        $prepareSource .= $valSource . $replace;
                        $replaceIsDone = true;
                    }
                }
                if ($replaceIsDone) {
                    $this->_sizeExplode++;
                    $replaceNumberOne = true;
                    $numberOfReplacements++;
                }
            }

            if (!$replaceIsDone) {
                if ($size < $sizeExplodeSource) {
                    $prepareSource .= $valSource . $replaceKeyword;
                } else {
                    $prepareSource .= $valSource;
                }
            }

            if ($this->currentNumberOfLinks + $numberOfReplacements == $this->getMaxLinkPerPage()) {
                $isStopReplacement = true;
            }
        }

        //to use maxReplacements  the desired number of times
        $this->addReplacementCount($originalReplaceKeyword, $numberOfReplacements);
        $this->currentNumberOfLinks = $this->currentNumberOfLinks + $numberOfReplacements;

        if ($direct == 1) {
            $prepareSource = strrev($prepareSource);
            $replace       = strrev($replace);
        }

        // return previously replaced part
        $prepareSource = preg_replace('#' . $replacementPlaceholder . '#i', $replace, $prepareSource) ?? $prepareSource;

        return $prepareSource;
    }

    /**
     * Get number of already done replacements for word on the page globally
     */
    protected function getReplacementCount(string $keyword): int
    {
        if (!isset($this->_replacementsCountGlobal[strtolower($keyword)])) {
            $this->_replacementsCountGlobal[strtolower($keyword)] = 0;
        }

        return $this->_replacementsCountGlobal[strtolower($keyword)];
    }

    /**
     * Increase number of already done replacements for word on the page globally
     */
    protected function addReplacementCount(string $keyword, int $cnt): void
    {
        if (!isset($this->_replacementsCountGlobal[strtolower($keyword)])) {
            $this->_replacementsCountGlobal[strtolower($keyword)] = 0;
        }
        $this->_replacementsCountGlobal[strtolower($keyword)] += $cnt;
    }

    public function checkSkipLinks(): bool
    {
        if ($this->_isSkipLinks === false) {
            return false;
        }
        if (!$skipLinks = $this->registry->registry('skip_auto_links')) {
            $skipLinks = $this->config->getSkipLinks((int)$this->storeManager->getStore()->getStoreId());
            if ($skipLinks) {
                $this->registry->register('skip_auto_links', $skipLinks);
            } else {
                $this->_isSkipLinks = false;
            }
        }
        if ($this->seoAutolinkPattern->checkArrayPattern(
            parse_url($this->context->getUrlBuilder()->getCurrentUrl(), PHP_URL_PATH),
            $skipLinks
        )
        ) {
            $this->_isSkipLinks = true;

            return true;
        }

        $this->_isSkipLinks = false;

        return false;
    }

    public function getExcludedAutoTags(): array
    {
        if (!$this->registry->registry('excluded_auto_links_tags') && $this->_isExcludedTags) {
            $excludedTags = $this->config->getExcludedTags($this->getStoreId());
            if ($excludedTags) {
                $this->registry->register('excluded_auto_links_tags', $excludedTags);
            } else {
                $this->_isExcludedTags = false;
            }
        } elseif ($this->_isExcludedTags) {
            $excludedTags = $this->registry->registry('excluded_auto_links_tags');
        }

        if (isset($excludedTags)) {
            return $excludedTags;
        }

        return [];
    }
}
