<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Helper;

use Exception;
use Magento\Backend\Block\Template;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\Read;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Core\Helper\Media;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Request\ReplyFactory;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;
use RuntimeException;

/**
 * Class Image
 * @package Mageplaza\RMA\Helper
 */
class Image extends Media
{
    const TEMPLATE_MEDIA_PATH = 'mageplaza/rma';
    const TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL = 'shipping_label';
    const TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL_FULL_PATH = 'mageplaza/rma/shipping_label';

    /**
     * @var File
     */
    protected $_ioFile;

    /**
     * @var Template
     */
    protected $_block;

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @var ReadFactory
     */
    protected $_readFactory;

    /**
     * @var FileFactory
     */
    protected $_fileFactory;

    /**
     * @var RawFactory
     */
    protected $_resultRawFactory;

    /**
     * @var RedirectFactory
     */
    protected $_resultRedirectFactory;

    /**
     * @var Json
     */
    protected $_resultJson;

    /**
     * @var RedirectInterface
     */
    protected $_redirect;

    /**
     * @var ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var ReplyFactory
     */
    protected $_replyFactory;

    /**
     * @var ReplyResource
     */
    protected $_replyResource;

    /**
     * Image constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param AdapterFactory $imageFactory
     * @param File $ioFile
     * @param Template $block
     * @param ReadFactory $readFactory
     * @param FileFactory $fileFactory
     * @param RawFactory $rawFactory
     * @param RedirectFactory $redirectFactory
     * @param Json $resultJson
     * @param RedirectInterface $redirect
     * @param ManagerInterface $messageManager
     * @param ReplyFactory $replyFactory
     * @param ReplyResource $replyResource
     *
     * @throws FileSystemException
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        AdapterFactory $imageFactory,
        File $ioFile,
        Template $block,
        ReadFactory $readFactory,
        FileFactory $fileFactory,
        RawFactory $rawFactory,
        RedirectFactory $redirectFactory,
        Json $resultJson,
        RedirectInterface $redirect,
        ManagerInterface $messageManager,
        ReplyFactory $replyFactory,
        ReplyResource $replyResource
    ) {
        $this->_ioFile = $ioFile;
        $this->_block = $block;
        $this->_filesystem = $filesystem;
        $this->_readFactory = $readFactory;
        $this->_fileFactory = $fileFactory;
        $this->_resultRawFactory = $rawFactory;
        $this->_resultRedirectFactory = $redirectFactory;
        $this->_resultJson = $resultJson;
        $this->_redirect = $redirect;
        $this->_messageManager = $messageManager;
        $this->_replyFactory = $replyFactory;
        $this->_replyResource = $replyResource;

        parent::__construct(
            $context,
            $objectManager,
            $storeManager,
            $filesystem,
            $uploaderFactory,
            $imageFactory
        );
    }

    /**
     * Get filename which is not duplicated with other files in media temporary and media directories
     *
     * @param string $fileName
     * @param string $descriptionPath
     *
     * @return string
     */
    public function getNotDuplicatedFilename($fileName, $descriptionPath)
    {
        $fileMediaName = $descriptionPath
            . '/' .
            Uploader::getNewFileName($this->mediaDirectory->getAbsolutePath($this->getMediaPath($fileName)));

        if ($fileMediaName !== $fileName) {
            return $this->getNotDuplicatedFilename($fileMediaName, $descriptionPath);
        }

        return $fileMediaName;
    }

    /**
     * Filesystem directory path of temporary product images
     * relatively to media folder
     *
     * @return string
     */
    public function getBaseTmpMediaPath()
    {
        return self::TEMPLATE_MEDIA_PATH . '/tmp';
    }

    /**
     * Part of URL of temporary product images
     * relatively to media folder
     *
     * @param string $file
     *
     * @return string
     */
    public function getTmpMediaPath($file)
    {
        return $this->getBaseTmpMediaPath() . '/' . $this->_prepareFile($file);
    }

    /**
     * @param string $file
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getTmpMediaUrl($file)
    {
        return $this->getBaseTmpMediaUrl() . '/' . $this->_prepareFile($file);
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getBaseTmpMediaUrl()
    {
        return $this->storeManager->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $this->getBaseTmpMediaPath();
    }

    /**
     * Get default icon image url
     *
     * @return mixed
     */
    public function getDefaultFileImage()
    {
        return $this->_block->getViewFileUrl('Mageplaza_RMA::media/file-default.svg');
    }

    /**
     * @param string $extension
     *
     * @return bool
     */
    public function isImageFile($extension)
    {
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'gif', 'png']);
    }

    /**
     * @param $imageEntries
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function processImagesGallery($imageEntries)
    {
        /** @var array $imageEntries */
        foreach ($imageEntries as $key => &$image) {
            if (!isset($image['file']) || !$image['file']) {
                unset($imageEntries[$key]);
                continue;
            }

            $fileName = $image['file'];
            $pos = strpos($fileName, '.tmp');

            if (isset($image['removed']) && $image['removed']) {
                /** Remove image */
                unset($imageEntries[$key]);

                if ($pos === false) {
                    $filePath = $this->getMediaPath($image['file']);
                    $file = $this->getMediaDirectory()->getRelativePath($filePath);
                    if ($this->getMediaDirectory()->isFile($file)) {
                        $this->getMediaDirectory()->delete($filePath);
                    }
                }
            } elseif ($pos !== false) {
                /** Move image from tmp folder */
                $fileName = substr($fileName, 0, $pos);
                $filePath = $this->getTmpMediaPath($fileName);
                $file = $this->getMediaDirectory()->getRelativePath($filePath);
                if (!$this->getMediaDirectory()->isFile($file)) {
                    unset($imageEntries[$key]);
                    continue;
                }

                $pathInfo = $this->_ioFile->getPathInfo($file);
                if (!isset($pathInfo['extension'])) {
                    unset($imageEntries[$key]);
                    continue;
                }

                $fileName = Uploader::getCorrectFileName($pathInfo['basename']);
                $dispretionPath = Uploader::getDispretionPath($fileName);
                $fileName = $dispretionPath . '/' . $fileName;

                $fileName = $this->getNotDuplicatedFilename($fileName, $dispretionPath);
                $destinationFile = $this->getMediaPath($fileName);

                try {
                    $this->getMediaDirectory()->renameFile($file, $destinationFile);
                    $image['file'] = str_replace('\\', '/', $fileName);
                } catch (Exception $e) {
                    throw new LocalizedException(__('We couldn\'t move this file: %1.', $e->getMessage()));
                }
            }

            if (isset($image['removed'])) {
                unset($image['removed']);
            }
        }

        return array_values($imageEntries);
    }

    /**
     * @param string $file
     * @param array $pathInfo
     * @param bool $isTmp
     *
     * @return mixed|string
     * @throws NoSuchEntityException
     */
    public function getFileImageUrl($file, $pathInfo, $isTmp = true)
    {
        if (!$this->isImageFile($pathInfo['extension'])) {
            $url = $this->getDefaultFileImage();
        } else {
            $url = $isTmp ? $this->getTmpMediaUrl($file)
                : $this->getBaseMediaUrl() . '/' . $this->getMediaPath($file);
        }

        return $url;
    }

    /**
     * @param array $params
     *
     * @return Raw|Redirect
     */
    public function downloadFile($params)
    {
        $resultRedirect = $this->_resultRedirectFactory->create();
        $resultRedirect->setPath($this->_redirect->getRefererUrl());

        if (isset($params['file_info'])) {
            $replyFileInfo = HelperData::jsonDecode($params['file_info']);
            $replyId = isset($replyFileInfo['reply_id']) ? $replyFileInfo['reply_id'] : 0;
            $fileId = isset($replyFileInfo['file_id']) ? $replyFileInfo['file_id'] : 0;
            $reply = $this->_replyFactory->create();
            $this->_replyResource->load($reply, $replyId);
            if (!$reply->getId()) {
                $this->_messageManager->addErrorMessage(__('Reply\'s File is not found.'));

                return $resultRedirect;
            }
            $replyFiles = HelperData::jsonDecode($reply->getFiles());
            $downloadableFiles = [];
            /** @var array $replyFile */
            foreach ($replyFiles as $replyFile) {
                $downloadableFiles[$replyFile['file_id']] = [
                    'location' => $this->getMediaPath($replyFile['file']),
                    'name' => $replyFile['name'],
                    'size' => $replyFile['size'],
                ];
            }
            if (!array_key_exists($fileId, $downloadableFiles)) {
                $this->_messageManager->addErrorMessage(__('File is not found.'));

                return $resultRedirect;
            }
            $fileInfo = $downloadableFiles[$fileId];
            try {
                $fileAbsolutePath = $fileInfo['location'];
                $mediaPath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
                $directoryRead = $this->_readFactory->create($mediaPath);
                $this->_fileFactory->create(
                    $fileInfo['name'],
                    null,
                    DirectoryList::PUB,
                    'application/octet-stream',
                    $fileInfo['size']
                );
                /** @var Raw $resultRaw */
                $resultRaw = $this->_resultRawFactory->create();
                $resultRaw->setContents($directoryRead->readFile($fileAbsolutePath));

                return $resultRaw;
            } catch (LocalizedException $e) {
                $this->_messageManager->addErrorMessage($e->getMessage());
            } catch (RuntimeException $e) {
                $this->_messageManager->addErrorMessage($e->getMessage());
            } catch (Exception $e) {
                $this->_messageManager->addExceptionMessage($e, __('Something went wrong while downloading the File.'));
            }
        }
        $this->_messageManager->addErrorMessage(__('Something went wrong while downloading the File.'));

        return $resultRedirect;
    }

    /**
     * @param array $allowedFiles
     * @param string|int $fileId
     *
     * @return Json
     */
    public function uploadReplyFile($allowedFiles, $fileId)
    {
        try {
            /**
             * Upload file to media directory
             */
            $uploader = $this->uploaderFactory->create(['fileId' => 'file']);
            $uploader->setAllowedExtensions($allowedFiles);
            if (!$uploader->checkAllowedExtension($uploader->getFileExtension())) {
                return $this->_resultJson->setData([
                    'status' => false,
                    'error' => __('File extension does not allowed.')
                ]);
            }
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            /**
             * @var Read $mediaDirectory
             */
            $mediaDirectory = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $result = $uploader->save($mediaDirectory->getAbsolutePath($this->getBaseTmpMediaPath()));

            unset($result['tmp_name'], $result['path']);
            $result['file_id'] = $fileId;
            $response = [
                'file_html' => $this->getFileInfoHtml($result),
                'status' => true
            ];
        } catch (Exception $e) {
            $response = [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }

        return $this->_resultJson->setData($response);
    }

    /**
     * @param array $result
     *
     * @return string
     */
    public function getFileInfoHtml($result)
    {
        $html = '<input class="" type="hidden" name="reply[files][' . $result['file_id'] . '][file_id]"
        value="' . $result['file_id'] . '"/>';
        $html .= '<input class="" type="hidden" name="reply[files][' . $result['file_id'] . '][file]"
        value="' . $result['file'] . '.tmp' . '"/>';
        $html .= '<input class="" type="hidden" name="reply[files][' . $result['file_id'] . '][location]"
        value="' . $this->getTmpMediaPath($result['file']) . '"/>';
        $html .= '<input class="" type="hidden" name="reply[files][' . $result['file_id'] . '][name]"
        value="' . $result['name'] . '"/>';
        $html .= '<input class="" type="hidden" name="reply[files][' . $result['file_id'] . '][size]"
        value="' . $result['size'] . '"/>';
        $html .= '<input class="" type="hidden" name="reply[files][' . $result['file_id'] . '][type]"
        value="' . $result['type'] . '"/>';

        return $html;
    }
}
