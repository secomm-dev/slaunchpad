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



namespace Mirasvit\Seo\Plugin\Frontend\Catalog\Block\Product\View\Gallery;

use Exception;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Filter\FilterManager;
use Mirasvit\Core\Helper\Io;
use Mirasvit\Core\Service\DegradationReporter;
use Mirasvit\Seo\Model\Config\ImageConfig;
use Mirasvit\Seo\Service\TemplateEngineService;

class SetMainImagePlugin
{
    private $imageConfig;

    private $templateEngineService;

    private $filterManager;

    private $mediaConfig;

    private $readFactory;

    private $filesystemHelper;

    private $degradationReporter;

    public function __construct(
        ImageConfig           $imageConfig,
        TemplateEngineService $templateEngineService,
        FilterManager         $filterManager,
        MediaConfig           $mediaConfig,
        ReadFactory           $readFactory,
        Io                    $filesystemHelper,
        DegradationReporter   $degradationReporter
    ) {
        $this->imageConfig           = $imageConfig;
        $this->templateEngineService = $templateEngineService;
        $this->filterManager         = $filterManager;
        $this->mediaConfig           = $mediaConfig;
        $this->readFactory           = $readFactory;
        $this->filesystemHelper      = $filesystemHelper;
        $this->degradationReporter   = $degradationReporter;
    }

    /**
     * @param mixed $subject
     * @param \Closure $closure
     * @param mixed $image
     * @return bool|mixed
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function aroundIsMainImage($subject, \Closure $closure, $image)
    {
        if ($this->imageConfig->isFriendlyUrlEnabled() && $subject->getProduct()->getImage()) {
            \Magento\Framework\Profiler::start(__METHOD__);
            $productImage = $this->getFriendlyImageName($subject->getProduct(), (string)$subject->getProduct()->getImage());
            \Magento\Framework\Profiler::stop(__METHOD__);

            return $image->getFile() == $productImage;
        } else {
            return $closure($image);
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string $fileName
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getFriendlyImageName($product, $fileName)
    {
        if (preg_match('@/image/\d[^/]*/@', $fileName)) {
            return $fileName; // the image is already user-friendly
        }

        $newFile = DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . $this->generateName($product, $fileName);

        $mediaDirectory = $this->readFactory->create(DirectoryList::MEDIA);
        $baseMediaPath  = $this->mediaConfig->getBaseMediaPath();

        $path    = $baseMediaPath . DIRECTORY_SEPARATOR . ltrim($fileName, DIRECTORY_SEPARATOR);
        $absPath = $mediaDirectory->getAbsolutePath($path);

        $newPath    = $baseMediaPath . DIRECTORY_SEPARATOR . ltrim($newFile, DIRECTORY_SEPARATOR);
        $absNewPath = $mediaDirectory->getAbsolutePath($newPath);

        try {
            if ($this->filesystemHelper->fileExists($absPath) && !$this->filesystemHelper->fileExists($absNewPath)) {
                $parentDirectory = $this->filesystemHelper->getParentDirectory($absNewPath);
                $this->filesystemHelper->mkdir($parentDirectory, 0777);
                $this->filesystemHelper->copy($absPath, $absNewPath);
            }
        } catch (Exception $e) {
            $this->degradationReporter->report('seo', 'image.friendly_copy_failed', $e->getMessage());

            return $fileName;
        }

        return $newFile;
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
