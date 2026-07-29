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



namespace Mirasvit\SeoSitemap\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Mirasvit\Seo\Helper\Data as HelperSeo;
use Mirasvit\SeoSitemap\Helper\Data as HelperData;
use Mirasvit\SeoSitemap\Helper\Markup as HelperMarkup;
use Mirasvit\SeoSitemap\Repository\ProviderRepository;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class Sitemap extends \Magento\Sitemap\Model\Sitemap
{
    private $providerRepository;

    private $helperSeo;

    private $seoSitemapData;

    private $helperMarkup;

    private $moduleManager;

    private $objectManager;

    private $tmpDirectory;

    private $alternates = null;

    private $currentEntityKey = null;

    protected $processedLinks = [];

    /**
     * Sitemap constructor.
     * @param HelperSeo $helperSeo
     * @param HelperData $seoSitemapData
     * @param HelperMarkup $helperMarkup
     * @param ProviderRepository $providerRepository
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Sitemap\Helper\Data $sitemapData
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Sitemap\Model\ResourceModel\Catalog\CategoryFactory $categoryFactory
     * @param \Magento\Sitemap\Model\ResourceModel\Catalog\ProductFactory $productFactory
     * @param \Magento\Sitemap\Model\ResourceModel\Cms\PageFactory $cmsFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $modelDate
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        HelperSeo $helperSeo,
        HelperData $seoSitemapData,
        HelperMarkup $helperMarkup,
        ProviderRepository $providerRepository,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Escaper $escaper,
        \Magento\Sitemap\Helper\Data $sitemapData,
        \Magento\Framework\Filesystem                                $filesystem,
        \Magento\Sitemap\Model\ResourceModel\Catalog\CategoryFactory $categoryFactory,
        \Magento\Sitemap\Model\ResourceModel\Catalog\ProductFactory  $productFactory,
        \Magento\Sitemap\Model\ResourceModel\Cms\PageFactory         $cmsFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime                  $modelDate,
        \Magento\Store\Model\StoreManagerInterface                   $storeManager,
        \Magento\Framework\App\RequestInterface                      $request,
        \Magento\Framework\Stdlib\DateTime                           $dateTime,
        \Magento\Framework\Module\Manager                            $moduleManager,
        \Magento\Framework\ObjectManagerInterface                    $objectManager,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource     $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb               $resourceCollection = null,
        array                                                        $data = []
    ) {
        $this->helperSeo          = $helperSeo;
        $this->seoSitemapData     = $seoSitemapData;
        $this->helperMarkup       = $helperMarkup;
        $this->providerRepository = $providerRepository;
        $this->moduleManager      = $moduleManager;
        $this->objectManager      = $objectManager;
        $this->tmpDirectory       = $filesystem->getDirectoryWrite(DirectoryList::SYS_TMP);

        parent::__construct(
            $context,
            $registry,
            $escaper,
            $sitemapData,
            $filesystem,
            $categoryFactory,
            $productFactory,
            $cmsFactory,
            $modelDate,
            $storeManager,
            $request,
            $dateTime,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @return void
     */
    protected function _initSitemapItems()
    {
        $storeId = $this->getStoreId();
        foreach ($this->providerRepository->initSitemapItems($storeId) as $items) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    $this->_sitemapItems[] = $item;
                }
            } elseif ($items) {
                $this->_sitemapItems[] = $items;
            }
        }

        $this->_tags = $this->helperMarkup->getTagsData();
    }

    /**
     * @return $this
     */
    public function generateXml()
    {
        $redirectHelper    = $this->getRedirectHelper();
        $progressBarSingle = false;
        $output            = null;

        if (php_sapi_name() == "cli") {
            $output            = new ConsoleOutput();
            $progressBarSingle = new ProgressBar($output);
            $progressBarSingle->setProgressCharacter('#');
            $progressBarSingle->setRedrawFrequency(100);
            $progressBarSingle->setFormat('%current% %bar% %memory:2s%');
            $progressBarSingle->start();
        }

        /** @var Config $config */
        $config = $this->objectManager->get(Config::class);

        if ($config->isSplitByEntity()) {
            $this->_tags = $this->helperMarkup->getTagsData();
            $this->generateXmlByEntity($progressBarSingle, $redirectHelper);
        } else {
            $this->_initSitemapItems();
            $this->processSitemapItems($this->_sitemapItems, $progressBarSingle, $redirectHelper);
            $this->_finalizeSitemap();

            if ($this->_sitemapIncrement == 1) {
                $path        = $this->getFilePath($this->_getCurrentSitemapFilename($this->_sitemapIncrement));
                $destination = $this->getFilePath($this->getSitemapFilename());
                $this->tmpDirectory->renameFile($path, $destination, $this->_directory);
            } else {
                $this->_createSitemapIndex();
            }
        }

        if ($progressBarSingle && $output !== null) {
            $progressBarSingle->finish();
            $output->write("\033[1A");
        }

        $this->setSitemapTime($this->_dateModel->gmtDate('Y-m-d H:i:s'));
        $this->save();

        return $this;
    }

    private function processSitemapItems(array $sitemapItems, $progressBarSingle, $redirectHelper): void
    {
        $rows = [];

        foreach ($sitemapItems as $sitemapItem) {
            $priority = $sitemapItem->getPriority();

            if ($sitemapItem instanceof \Magento\Sitemap\Model\SitemapItem) {
                $changeFreq = $sitemapItem->getChangeFrequency();
                if ($this->seoSitemapData->checkIsUrlExcluded($sitemapItem->getUrl(), $this->getStoreId())) {
                    continue;
                }
                $rows[] = [
                    'item'       => $sitemapItem,
                    'changeFreq' => $changeFreq,
                    'priority'   => $priority,
                    'url'        => (string)$sitemapItem->getUrl(),
                ];
            } else {
                $changeFreq = $sitemapItem->getChangefreq();

                foreach ($sitemapItem->getCollection() as $item) {
                    if ($this->seoSitemapData->checkIsUrlExcluded($item->getUrl(), $this->getStoreId())) {
                        continue;
                    }
                    $rows[] = [
                        'item'       => $item,
                        'changeFreq' => $changeFreq,
                        'priority'   => $priority,
                        'url'        => (string)$item->getUrl(),
                    ];
                }
            }
        }

        /** @var Config $config */
        $config = $this->objectManager->get(Config::class);
        if ($config->isSortByPriority($this->getStoreId())) {
            $rows = $this->sortRowsByPriority($rows);
        }

        foreach ($rows as $row) {
            $this->addSitemapItemRow(
                $row['item'],
                $row['changeFreq'],
                $row['priority'],
                $progressBarSingle,
                $redirectHelper
            );
        }
    }

    /**
     * Sort collected sitemap rows by priority value (highest first), using the URL
     * string as the secondary sort key (ascending) for equal-priority entries. The
     * secondary key is the prepared (rendered) URL so the tie-break matches the
     * order URLs actually appear in the generated <loc> elements.
     *
     * @param array $rows
     * @return array
     */
    private function sortRowsByPriority(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['sortUrl'] = $this->getPreparedUrl(
                (string)$row['url'],
                \Magento\Framework\UrlInterface::URL_TYPE_LINK
            );
        }
        unset($row);

        usort($rows, function (array $a, array $b) {
            $priorityComparison = (float)$b['priority'] <=> (float)$a['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return strcmp($a['sortUrl'], $b['sortUrl']);
        });

        return $rows;
    }

    private function generateXmlByEntity($progressBarSingle, $redirectHelper): void
    {
        $indexEntries = [];

        foreach ($this->providerRepository->getProvidersGroupedByKey() as $key => $providers) {
            $this->currentEntityKey  = $key;
            $this->_sitemapIncrement = 0;
            $this->_fileSize         = 0;
            $this->_lineCount        = 0;

            $entityItems = [];
            foreach ($providers as $provider) {
                $result = $provider->initSitemapItem($this->getStoreId());
                if (empty($result)) {
                    continue;
                }
                if (is_array($result)) {
                    foreach ($result as $item) {
                        $entityItems[] = $item;
                    }
                } else {
                    $entityItems[] = $result;
                }
            }

            if (empty($entityItems)) {
                continue;
            }

            $this->processSitemapItems($entityItems, $progressBarSingle, $redirectHelper);

            if ($this->_sitemapIncrement === 0) {
                continue;
            }

            $this->_finalizeSitemap();

            $entityIncrement = $this->_sitemapIncrement;

            if ($entityIncrement === 1) {
                $srcPath  = $this->getFilePath($this->getEntityFilename($key, 1));
                $destName = $this->resolveEntityKey($key) . '-' . pathinfo($this->getSitemapFilename(), PATHINFO_FILENAME) . '.xml';
                $this->tmpDirectory->renameFile($srcPath, $this->getFilePath($destName), $this->_directory);
                $indexEntries[] = $destName;
            } else {
                for ($i = 1; $i <= $entityIncrement; $i++) {
                    $srcFileName  = $this->getEntityFilename($key, $i);
                    $destFileName = $this->getEntityFilename($this->resolveEntityKey($key), $i);
                    $srcPath      = $this->getFilePath($srcFileName);
                    $destPath     = $this->getFilePath($destFileName);
                    $this->tmpDirectory->renameFile($srcPath, $destPath, $this->_directory);
                    $indexEntries[] = $destFileName;
                }
            }
        }

        $this->currentEntityKey = null;

        $this->_createSitemap($this->getSitemapFilename(), self::TYPE_INDEX);
        foreach ($indexEntries as $fileName) {
            $this->_writeSitemapRow($this->_getSitemapIndexRow($fileName, $this->_getCurrentDateTime()));
        }
        $this->_finalizeSitemap(self::TYPE_INDEX);
        $path = $this->getFilePath($this->getSitemapFilename());
        $this->tmpDirectory->renameFile($path, $path, $this->_directory);
    }

    protected function _getCurrentSitemapFilename($index): string
    {
        if ($this->currentEntityKey !== null) {
            return $this->getEntityFilename($this->currentEntityKey, $index);
        }
        return parent::_getCurrentSitemapFilename($index);
    }

    private function resolveEntityKey(string $key): string
    {
        if ($key !== 'other') {
            return $key;
        }

        /** @var Config $config */
        $config     = $this->objectManager->get(Config::class);
        $customName = $config->getAdditionalSitemapName((string)$this->getStoreId());

        return $customName !== '' ? $customName : $key;
    }

    private function getEntityFilename(string $key, int $increment): string
    {
        $base = pathinfo($this->getSitemapFilename(), PATHINFO_FILENAME);
        return $key . '-' . $base . '-' . $increment . '.xml';
    }

    /**
     * @param mixed $item
     * @param string $changeFreq
     * @param string $priority
     * @param mixed $progressBarSingle
     * @param mixed $redirectHelper
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function addSitemapItemRow($item, $changeFreq, $priority, $progressBarSingle, $redirectHelper)
    {
        if ($redirectHelper && !($item instanceof \Magento\Sitemap\Model\SitemapItem)) {
            $url = $redirectHelper->getUrlWithCorrectEndSlash($item->getUrl());
            $item->setUrl($url);
        }

        if (is_object($item) && method_exists($item, 'getData') && $item->getAlternates()) {
            $this->alternates = $this->getRowAlternateLinksMarkup($item->getAlternates());
        } else {
            $this->alternates = null;
        }

        $xml = $this->_getSitemapRow($item->getUrl(), $item->getUpdatedAt(), $changeFreq, $priority, $item->getImages(), $item->getVideos());
        if ($xml) {
            if ($this->_isSplitRequired($xml) && $this->_sitemapIncrement > 0) {
                $this->_finalizeSitemap();
            }

            if (!$this->_fileSize) {
                $this->_createSitemap();
            }
            $this->_writeSitemapRow($xml);
            $this->_lineCount++;
            $this->_fileSize += strlen($xml);
        }
        if ($progressBarSingle) {
            $progressBarSingle->advance();
        }
    }

    /**
     * @param string $url
     * @param string|null $lastmod
     * @param string|null $changefreq
     * @param string|null $priority
     * @param \Magento\Framework\DataObject|null $images
     * @param \Magento\Framework\DataObject[]|null $videos
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _getSitemapRow($url, $lastmod = null, $changefreq = null, $priority = null, $images = null, $videos = null)
    {
        $url = htmlspecialchars($this->getPreparedUrl($url, \Magento\Framework\UrlInterface::URL_TYPE_LINK));

        $urlHash = hash('sha256', $url);
        if (isset($this->processedLinks[$urlHash])) {
            return '';
        }
        $this->processedLinks[$urlHash] = true;

        $row = '<loc>' . $url . '</loc>';
        if ($lastmod) {
            $row .= '<lastmod>' . $this->_getFormattedLastmodDate($lastmod) . '</lastmod>';
        }

        /** @var Config $config */
        $config = $this->objectManager->get(Config::class);

        if (!$config->removeChangefreqAndPriority()) {
            if ($changefreq) {
                $row .= '<changefreq>' . $changefreq . '</changefreq>';
            }
            if ($priority) {
                $row .= sprintf('<priority>%.1F</priority>', $priority);
            }
        }

        if ($images) {
            $row .= $this->getRowImageMarkup($images);
        }

        if ($videos) {
            $row .= $this->getRowVideoMarkup($videos);
        }

        if ($this->alternates) {
            $row .= $this->alternates;
        }

        return '<url>' . $row . '</url>';
    }

    /**
     * @param \Magento\Framework\DataObject $images
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getRowImageMarkup($images)
    {
        $imagesMarkup = [];
        foreach ($images->getCollection() as $image) {
            $imagesMarkup[] = $this->helperMarkup->getImageMarkup(
                htmlspecialchars((string)$this->getPreparedUrl($image->getUrl())),
                htmlspecialchars((string)$images->getTitle()),
                htmlspecialchars((string)$image->getCaption())
            );
        }

        $imagesMarkup[] = $this->helperMarkup->afterGetImageMarkup(
            htmlspecialchars((string)$images->getTitle()),
            htmlspecialchars((string)$this->getPreparedUrl($images->getThumbnail())),
            htmlspecialchars((string)$images->getAlt())
        );

        return implode('', $imagesMarkup);
    }

    /**
     * @param \Magento\Framework\DataObject[] $videos
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getRowVideoMarkup($videos)
    {
        $videosMarkup = [];
        foreach ($videos as $video) {
            $videosMarkup[] = $this->helperMarkup->getVideoMarkup(
                htmlspecialchars((string)$this->getPreparedUrl($video->getThumbnail())),
                htmlspecialchars((string)$video->getTitle()),
                htmlspecialchars((string)$video->getDescription()),
                htmlspecialchars((string)$video->getPlayerLoc())
            );
        }

        return implode('', $videosMarkup);
    }

    /**
     * @param string $url
     * @param string $type
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getPreparedUrl($url, $type = \Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
    {
        if (strpos($url, 'http://') !== false
            || strpos($url, 'https://') !== false) {
            $baseUrl = $this->_storeManager->getStore($this->getStoreId())
                ->getBaseUrl($type);
            if (strpos($url, $baseUrl) === false) {
                $url = preg_replace('/(.*?)\\/media\\//ims', $baseUrl . '/', $url, 1) ?? $url;
            }
            $urlExploded    = explode('://', $url);
            $urlExploded[1] = str_replace('//', '/', $urlExploded[1]);
            $url            = implode('://', $urlExploded);
            if (strpos($baseUrl, '/pub/') === false) {
                $url = str_replace('/pub/', '/', $url);
            }

            if ($this->isStoreSecure()) {
                $url = str_replace('http://', 'https://', $url);
            }

            return $url;
        }

        $url = ltrim($url, '/');

        $storeCode = $this->_storeManager->getStore($this->getStoreId())->getCode();

        if (strpos($url, $storeCode . '/') === 0) {
            $url = substr($url, strlen($storeCode) + 1);
        }

        return $this->_getUrl($url, $type);
    }

    /**
     * @return \Mirasvit\Seo\Helper\Redirect|bool
     */
    public function getRedirectHelper()
    {
        return $this->moduleManager->isEnabled('Mirasvit_Seo') ?
            $this->objectManager->get('\Mirasvit\Seo\Helper\Redirect') : false;
    }

    /**
     * @param mixed $sitemapFilename
     * @param string|null $lastmod
     * @return string
     */
    protected function _getSitemapIndexRow($sitemapFilename, $lastmod = null)
    {
        $sitemapPath = str_replace('/pub/', '/', $this->getSitemapPath());
        $url = $this->getSitemapUrl($sitemapPath, $sitemapFilename);
        $row = '<loc>' . htmlspecialchars($url) . '</loc>';
        if ($lastmod) {
            $row .= '<lastmod>' . $this->_getFormattedLastmodDate($lastmod) . '</lastmod>';
        }

        return '<sitemap>' . $row . '</sitemap>';
    }

    private function getRowAlternateLinksMarkup(array $storeUrls): string
    {
        $alternateLinksMarkup = [];
        $isSecure             = $this->isStoreSecure();

        foreach ($storeUrls as $storeId => $url) {
            if ($isSecure) {
                $url = str_replace('http://', 'https://', $url);
            }

            $hreflang = $this->helperSeo->getHreflang($storeId);

            if (!is_array($hreflang)) {
                $hreflang = [$hreflang];
            }

            foreach ($hreflang as $lang) {
                $alternateLinksMarkup[] = $this->helperMarkup->getAlternateLinkMarkup(
                    htmlspecialchars((string)$lang),
                    htmlspecialchars((string)$url)
                );
            }
        }

        return implode('', $alternateLinksMarkup);
    }

    private function isStoreSecure(): bool
    {
        $store = $this->_storeManager->getStore($this->getStoreId());

        return $store->isUrlSecure();
    }

    /**
     * Get path to sitemap file
     *
     * @param string $fileName
     * @return string
     */
    private function getFilePath(string $fileName): string
    {
        $path = $this->getSitemapPath() !== null ? rtrim($this->getSitemapPath(), '/') : '';
        $path .= '/' . $fileName;

        return $path;
    }

    /**
     * Generate sitemap index XML file
     *
     * @return void
     */
    protected function _createSitemapIndex()
    {
        $this->_createSitemap($this->getSitemapFilename(), self::TYPE_INDEX);
        for ($i = 1; $i <= $this->_sitemapIncrement; $i++) {
            $fileName = $this->_getCurrentSitemapFilename($i);
            $path = $this->getFilePath($fileName);
            $this->tmpDirectory->renameFile($path, $path, $this->_directory);
            $xml = $this->_getSitemapIndexRow($fileName, $this->_getCurrentDateTime());
            $this->_writeSitemapRow($xml);
        }
        $this->_finalizeSitemap(self::TYPE_INDEX);
        $path = $this->getFilePath($this->getSitemapFilename());
        $this->tmpDirectory->renameFile($path, $path, $this->_directory);
    }

    /**
     * Create new sitemap file
     *
     * @param null|string $fileName
     * @param string $type
     * @return void
     * @throws LocalizedException
     */
    protected function _createSitemap($fileName = null, $type = self::TYPE_URL)
    {
        if (!$fileName) {
            $this->_sitemapIncrement++;
            $fileName = $this->_getCurrentSitemapFilename($this->_sitemapIncrement);
        }
        $path = $this->getFilePath($fileName);
        $this->_stream = $this->tmpDirectory->openFile($path);

        $fileHeader = sprintf($this->_tags[$type][self::OPEN_TAG_KEY], $type);
        $this->_stream->write($fileHeader);
        $this->_fileSize = strlen($fileHeader . sprintf($this->_tags[$type][self::CLOSE_TAG_KEY], $type));
    }
}
