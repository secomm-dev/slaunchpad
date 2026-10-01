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
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\Uploader;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;

/**
 * Class FileProcessor
 * @package Mageplaza\RMA\Helper
 */
class FileProcessor
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Uploader
     */
    protected $uploader;

    /**
     * @var WriteInterface
     */
    protected $mediaDirectory;

    /**
     * @var File
     */
    protected $ioFile;

    /**
     * FileProcessor constructor.
     *
     * @param Filesystem $fileSystem
     * @param LoggerInterface $logger
     * @param Uploader $uploader
     * @param File $ioFile
     *
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $fileSystem,
        LoggerInterface $logger,
        Uploader $uploader,
        File $ioFile
    ) {
        $this->filesystem = $fileSystem;
        $this->logger = $logger;
        $this->uploader = $uploader;
        $this->ioFile = $ioFile;
        $this->mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @param string $entityType
     * @param ImageContentInterface|array $file
     *
     * @return array
     * @throws FileSystemException
     */
    public function processFileContent($entityType, $file)
    {
        $isApi = is_array($file);
        $fileContent = base64_decode($isApi ? $file['base64_encoded_data'] : $file->getBase64EncodedData(), true);
        $tmpDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::SYS_TMP);
        $fileName = $this->getFileName($file);
        $tmpDirectory->writeFile($fileName, $fileContent);
        $fileAttributes = [
            'tmp_name' => $tmpDirectory->getAbsolutePath() . $fileName,
            'name' => $fileName
        ];

        try {
            $this->uploader->processFileAttributes($fileAttributes);
            $this->uploader->setFilesDispersion(true);
            $this->uploader->setFilenamesCaseSensitivity(false);
            $this->uploader->setAllowRenameFiles(true);
            $this->uploader->save($this->mediaDirectory->getAbsolutePath($entityType), $fileName);
        } catch (Exception $e) {
            $this->logger->critical($e);
        }

        return [
            'file_path' => $this->uploader->getUploadedFileName(),
            'file_type' => $this->ioFile->getPathInfo($fileName),
            'size' => strlen($fileContent)
        ];
    }

    /**
     * @param ImageContentInterface|array $imageContent
     *
     * @return string
     */
    public function getFileName($imageContent)
    {
        $fileName = is_array($imageContent) ? $imageContent['name'] : $imageContent->getName();
        $pathInfo = $this->ioFile->getPathInfo($fileName);

        if (!$pathInfo) {
            $fileName .= '.' . $pathInfo['extension'];
        }

        return $fileName;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isNameValid($name)
    {
        // Cannot contain \ / ? * : " ; < > ( ) | { }
        if (!preg_match('/^[^\\/?*:";<>()|{}\\\\]+$/', $name)) {
            return false;
        }

        return true;
    }
}
