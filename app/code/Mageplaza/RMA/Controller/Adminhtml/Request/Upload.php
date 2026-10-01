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

namespace Mageplaza\RMA\Controller\Adminhtml\Request;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image;

/**
 * Class Upload
 * @package Mageplaza\RMA\Controller\Adminhtml\Request
 */
class Upload extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var UploaderFactory
     */
    protected $_uploaderFactory;

    /**
     * @var Filesystem
     */
    protected $_fileSystem;

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
     * @param Action\Context $context
     * @param RawFactory $resultRawFactory
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param File $ioFile
     * @param Image $imageHelper
     * @param HelperData $helperData
     */
    public function __construct(
        Action\Context $context,
        RawFactory $resultRawFactory,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        File $ioFile,
        Image $imageHelper,
        HelperData $helperData
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->_uploaderFactory = $uploaderFactory;
        $this->_fileSystem = $filesystem;
        $this->_ioFile = $ioFile;
        $this->_imageHelper = $imageHelper;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return Raw
     */
    public function execute()
    {
        try {
            $uploader = $this->_uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions($this->_helperData->getAllowedFileExtensions());
            $uploader->checkAllowedExtension($uploader->getFileExtension());
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            /** var Read $mediaDirectory */
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

            $result['file'] .= '.tmp';
            $result['file_type'] = $this->_imageHelper->isImageFile($pathInfo['extension']) ? 'image' : 'others';
            $result['file_info'] = HelperData::jsonEncode($fileInfo);
        } catch (Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        /** @var Raw $response */
        $response = $this->resultRawFactory->create();
        $response->setHeader('Content-type', 'text/plain');
        $response->setContents(Image::jsonEncode($result));

        return $response;
    }
}
