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

namespace Mageplaza\RMA\Controller\Request;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\Read;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\View\Result\PageFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Mageplaza\RMA\Block\Request\Index\Images;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image;

/**
 * Class Upload
 * @package Mageplaza\RMA\Controller\Request
 */
class Upload extends Action
{
    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var UploaderFactory
     */
    protected $_uploaderFactory;

    /**
     * @var Filesystem
     */
    protected $_fileSystem;

    /**
     * @var Json
     */
    protected $_resultJson;

    /**
     * @var File
     */
    protected $_ioFile;

    /**
     * @var Image
     */
    protected $_imageHelper;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Upload constructor.
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param Json $resultJson
     * @param File $ioFile
     * @param Image $imageHelper
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        Json $resultJson,
        File $ioFile,
        Image $imageHelper,
        HelperData $helperData
    ) {
        $this->_resultPageFactory = $pageFactory;
        $this->_uploaderFactory = $uploaderFactory;
        $this->_fileSystem = $filesystem;
        $this->_resultJson = $resultJson;
        $this->_ioFile = $ioFile;
        $this->_imageHelper = $imageHelper;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $page = $this->_resultPageFactory->create();
        $fileId = $this->getRequest()->getParam('file_id');
        $position = (int)$this->getRequest()->getParam('position');
        $maxImageSize = $this->_helperData->getMaxFileSize();
        try {
            /**
             * Upload file to media directory
             */
            $uploader = $this->_uploaderFactory->create(['fileId' => 'file']);
            $uploader->setAllowedExtensions($this->_helperData->getAllowedFileExtensions());
            if (!$uploader->checkAllowedExtension($uploader->getFileExtension())) {
                return $this->_resultJson->setData([
                    'status' => false,
                    'errorSize' => __('File extension does not allowed.')
                ]);
            }
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            /**
             * @var Read $mediaDirectory
             */
            $mediaDirectory = $this->_fileSystem->getDirectoryRead(DirectoryList::MEDIA);
            $result = $uploader->save($mediaDirectory
                ->getAbsolutePath($this->_imageHelper->getBaseTmpMediaPath()));
            unset($result['tmp_name'], $result['path']);
            $pathInfo = $this->_ioFile->getPathInfo($result['file']);
            $result['url'] = $this->_imageHelper->getFileImageUrl($result['file'], $pathInfo);
            $fileInfo = [
                'location' => $this->_imageHelper->getTmpMediaPath($result['file']),
                'name' => $result['name'],
                'size' => $result['size']
            ];
            /**
             * Set return data to file gallery
             */
            $data = [
                'file_id' => $fileId,
                'file_info' => HelperData::jsonEncode($fileInfo),
                'file_type' => $this->_imageHelper->isImageFile($pathInfo['extension']) ? 'image' : 'others',
                'name' => $result['name'],
                'size' => $result['size'],
                'file' => $result['file'] . '.tmp',
                'url' => $result['url'],
                'position' => $position + 1
            ];
            $this->_eventManager->dispatch(
                'mprma_request_attachment_upload_file_after',
                ['result' => $data, 'action' => $this]
            );
            /** @var Images $imageBlock */
            $imageBlock = $page->getLayout()->createBlock(Images::class);
            $template = $this->_helperData->checkHyvaTheme()
                ? 'Mageplaza_RMA::hyva/request/index/images.phtml'
                : 'Mageplaza_RMA::request/index/images.phtml';

            $response = [
                'request_files' => $imageBlock
                    ->setFileData($data)
                    ->setTemplate($template)
                    ->toHtml(),
                'status' => true
            ];

        } catch (Exception $e) {
            $response = [
                'status' => false,
                'error' => $e->getMessage(),
                'errorCode' => $e->getCode(),
                'errorSize' => __('Make sure your file isn\'t more than %1M.', $maxImageSize)
            ];
        }

        return $this->_resultJson->setData($response);
    }
}
