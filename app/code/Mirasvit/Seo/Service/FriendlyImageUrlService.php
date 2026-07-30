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



namespace Mirasvit\Seo\Service;

use Magento\Framework\Filesystem\Directory\ReadFactory;
use Mirasvit\Core\Helper\Io;
use Mirasvit\Seo\Api\Service\FriendlyImageUrlServiceInterface;
use Mirasvit\Seo\Model\Config\ImageConfig;
use Magento\Framework\Filter\FilterManager;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\App\Filesystem\DirectoryList;

class FriendlyImageUrlService implements FriendlyImageUrlServiceInterface
{
    private $imageConfig;

    private $templateEngineService;

    private $filterManager;

    private $mediaConfig;

    private $directoryList;

    private $cache = [];

    private $cacheAlt = [];

    private $cacheTitle = [];

    private $readFactory;

    private $filesystemHelper;

    /**
     * FriendlyImageUrlService constructor.
     * @param ImageConfig $imageConfig
     * @param TemplateEngineService $templateEngineService
     * @param FilterManager $filterManager
     * @param MediaConfig $mediaConfig
     * @param DirectoryList $directoryList
     */
    public function __construct(
        ImageConfig $imageConfig,
        TemplateEngineService $templateEngineService,
        FilterManager $filterManager,
        MediaConfig $mediaConfig, //can't use MediaConfigInterface, because of magento tests error in M2.1
        DirectoryList $directoryList,
        ReadFactory           $readFactory,
        Io                    $filesystemHelper
    ) {
        $this->imageConfig           = $imageConfig;
        $this->templateEngineService = $templateEngineService;
        $this->filterManager         = $filterManager;
        $this->mediaConfig           = $mediaConfig;
        $this->directoryList         = $directoryList;
        $this->readFactory           = $readFactory;
        $this->filesystemHelper      = $filesystemHelper;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string $fileName
     * @return string
     */
    public function getFriendlyImageName($product, $fileName)
    {
        if (isset($this->cache[$product->getId()][$fileName])) {
            return $this->cache[$product->getId()][$fileName];
        } else {
            $this->cache[$product->getId()] = [];
        }

        if (!$fileName) {
            return $fileName;
        }

        // swatch images fix (processed already)
        if (strpos($fileName, DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR) !== false) {
            $this->cache[$product->getId()][$fileName] = $fileName;
            return $fileName;
        }

        \Magento\Framework\Profiler::start(__METHOD__);
        $newFile = DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . $this->generateName($product, $fileName);

        $mediaDirectory = $this->readFactory->create(DirectoryList::MEDIA);
        $baseMediaPath  = $this->mediaConfig->getBaseMediaPath();

        $path    = $baseMediaPath . DIRECTORY_SEPARATOR . ltrim($fileName, DIRECTORY_SEPARATOR);
        $absPath = $mediaDirectory->getAbsolutePath($path);

        $newPath    = $baseMediaPath . DIRECTORY_SEPARATOR . ltrim($newFile, DIRECTORY_SEPARATOR);
        $absNewPath = $mediaDirectory->getAbsolutePath($newPath);

        try {
            if ($this->filesystemHelper->fileExists($absPath)) {
                if (!$this->filesystemHelper->fileExists($absNewPath)) {
                    $parentDirectory = $this->filesystemHelper->getParentDirectory($absNewPath);
                    $this->filesystemHelper->mkdir($parentDirectory, 0777);
                    $this->filesystemHelper->copy($absPath, $absNewPath);
                }
            } else { // use old file name of the image does not exist (swatches issue)
                $newFile = $fileName;
            }
        } catch (\Exception $e) {
            $this->cache[$product->getId()][$fileName] = $fileName;
            return $fileName;
        }
        $this->cache[$product->getId()][$fileName] = $newFile;
        \Magento\Framework\Profiler::stop(__METHOD__);
        return $newFile;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param int|null $storeId
     *
     * @return string
     */
    public function getFriendlyImageAlt($product, $storeId = null)
    {
        $altKey = 'alt_' . $product->getId();

        $storeId = $storeId ?: $product->getStoreId();

        if ($storeId) {
            $altKey .= '_' . $storeId;
        }

        if (isset($this->cacheAlt[$altKey])) {
            return $this->cacheAlt[$altKey];
        }

        \Magento\Framework\Profiler::start(__METHOD__);
        $template = $this->imageConfig->getAltTemplate();

        $res = $this->templateEngineService->render($template, ['product' => $product]);
        $this->cacheAlt[$altKey] = $res;
        \Magento\Framework\Profiler::stop(__METHOD__);

        return $res;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param int|null $storeId
     *
     * @return string
     */
    public function getFriendlyImageTitle($product, $storeId = null)
    {
        $titleKey = 'title_' . $product->getId();

        $storeId = $storeId ?: $product->getStoreId();

        if ($storeId) {
            $titleKey .= '_' . $storeId;
        }

        if (isset($this->cacheTitle[$titleKey])) {
            return $this->cacheTitle[$titleKey];
        }

        \Magento\Framework\Profiler::start(__METHOD__);
        $template = $this->imageConfig->getTitleTemplate();

        $res = $this->templateEngineService->render($template, ['product' => $product]);
        $this->cacheTitle[$titleKey] = $res;
        \Magento\Framework\Profiler::stop(__METHOD__);

        return $res;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string                         $fileName
     *
     * @return string
     */
    private function generateName($product, $fileName)
    {
        $imageUrlTemplate = $this->imageConfig->getUrlTemplate();

        $label     = $this->templateEngineService->render($imageUrlTemplate, ['product' => $product]);

        $imageName = $this->filterManager->translitUrl($label);
        $suffix    = preg_replace('/(.*)(\\.)/', '.', $fileName);

        $imagePath = $product->getId() . substr(hash('sha256', $fileName), 4, 4);

        return $imagePath . '/' . $imageName . $suffix;
    }
}
