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

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\View\Element\AbstractBlock;
use Mageplaza\RMA\Block\Adminhtml\Media\Uploader;
use Mageplaza\RMA\Helper\Data;
use Mageplaza\RMA\Helper\Image;

/**
 * Class Images
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form
 */
class Files extends Widget
{
    /**
     * @var File
     */
    protected $_ioFile;

    /**
     * @var Image
     */
    public $imageHelper;

    /**
     * Images constructor.
     *
     * @param Context $context
     * @param File $ioFile
     * @param Image $imageHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        File $ioFile,
        Image $imageHelper,
        array $data = []
    ) {
        $this->_ioFile = $ioFile;
        $this->imageHelper = $imageHelper;

        parent::__construct($context, $data);
    }

    /**
     * @return AbstractBlock
     */
    protected function _prepareLayout()
    {
        $this->addChild('uploader', Uploader::class);

        $this->getUploader()->getConfig()->setUrl(
            $this->_urlBuilder->getUrl('mprma/request/upload')
        )->setFileField(
            'image'
        )->setFilters([
            'all' => ['label' => __('All Files'), 'files' => ['*.*']]
        ]);

        return parent::_prepareLayout();
    }

    /**
     * Retrieve uploader block
     *
     * @return bool|Uploader
     */
    public function getUploader()
    {
        return $this->getChildBlock('uploader');
    }

    /**
     * Retrieve uploader block html
     *
     * @return string
     */
    public function getUploaderHtml()
    {
        return $this->getChildHtml('uploader');
    }

    /**
     * @return string
     */
    public function getJsObjectName()
    {
        return $this->getHtmlId() . 'JsObject';
    }

    /**
     * @return string
     */
    public function getAddImagesButton()
    {
        return $this->getButtonHtml(
            __('Add New Files'),
            $this->getJsObjectName() . '.showUploader()',
            'add',
            $this->getHtmlId() . '_add_images_button'
        );
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getFilesJson()
    {
        $value = $this->getElement()->getFiles();
        if (!is_array($value) || empty($value)) {
            return '[]';
        }
        $mediaDir = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $files = $this->sortFilesByPosition($value);
        foreach ($files as $key => &$file) {
            $pathInfo = $this->_ioFile->getPathInfo($file['file']);
            $file['url'] = $this->imageHelper->getFileImageUrl($file['file'], $pathInfo, false);
            $file['file_type'] = $this->imageHelper->isImageFile($pathInfo['extension']) ? 'image' : 'others';
            $file['file_info'] = Data::jsonEncode([
                'location' => $this->imageHelper->getMediaPath($file['file']),
                'name' => $file['name'],
                'size' => $file['size']
            ]);
            try {
                $fileHandler = $mediaDir->stat($this->imageHelper->getMediaPath($file['file']));
                $file['size'] = $fileHandler['size'];
            } catch (Exception $e) {
                $this->_logger->warning($e);
                unset($files[$key]);
            }
        }

        return Data::jsonEncode($files);
    }

    /**
     * Sort files array by position key
     *
     * @param array $files
     *
     * @return array
     */
    private function sortFilesByPosition($files)
    {
        usort($files, function ($fileA, $fileB) {
            return ($fileA['position'] < $fileB['position']) ? -1 : 1;
        });

        return $files;
    }

    /**
     * Get file types data
     *
     * @return array
     */
    public function getFileTypes()
    {
        return [
            'image' => [
                'code' => 'images',
                'value' => $this->getElement()->getDataObject()
                    ? $this->getElement()->getDataObject()->getFiles() : '',
                'label' => 'Template Images',
                'scope' => 'Template Images',
                'name' => 'template-images',
            ]
        ];
    }
}
