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
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\Core\Helper;

use Exception;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\ObjectManagerInterface;

class Io extends AbstractHelper
{
    /**
     * @var DriverInterface
     */
    public $driver;

    /**
     * @var bool
     */
    private $isRemoteStorageEnabled = false;

    public function __construct(
        DeploymentConfig $config,
        Context $context,
        File $file,
        ObjectManagerInterface $objectManager
    ) {
        $awsS3 = 'Magento\AwsS3\Driver\AwsS3';
        $remoteDriverPool = 'Magento\RemoteStorage\Driver\DriverPool';

        if (
            class_exists($remoteDriverPool)
            && $config->get(\Magento\RemoteStorage\Driver\DriverPool::PATH_DRIVER) === 'aws-s3'
            && class_exists($awsS3)
        ) {
            $this->driver = $objectManager->create(\Magento\AwsS3\Driver\AwsS3Factory::class)->create();
            $this->isRemoteStorageEnabled = true;
        } else {
            $this->driver = $file;
        }

        parent::__construct($context);
    }

    public function write(string $filename, string $content, string $mode = 'w'): self
    {
        if ($this->isRemoteStorageEnabled() && $mode === 'a') {
            $content = $this->fileGetContents($filename) . $content;
        }

        if ($this->isRemoteStorageEnabled() && in_array($mode, ['w', 'a'])) {
            $this->filePutContents($filename, '');
        }

        $fp = $this->driver->fileOpen($filename, $mode);
        if ($this->isWin()) {
            $this->driver->fileWrite($fp, $content);
        } else {
            $this->driver->fileLock($fp);
            $this->driver->fileWrite($fp, $content);
            $this->driver->fileUnlock($fp);
        }
        $this->driver->fileClose($fp);

        if (!$this->driver->isWritable($filename)) {
            $this->driver->changePermissions($filename, 0777);
        }

        if (!$this->fileExists($filename)) {
            throw new Exception(sprintf('File %s not created.', $filename));
        }

        return $this;
    }

    public function copy(string $from, string $to): self
    {
        if (!$this->fileExists($from)) {
            throw new Exception(sprintf('File %s not exists.', $from));
        }

        $this->driver->copy($from, $to);

        if (!$this->driver->isWritable($to)) {
            $this->driver->changePermissions($to, 0777);
        }

        if (!$this->fileExists($to)) {
            throw new Exception(sprintf('File %s not copied to %s', $from, $to));
        }

        return $this;
    }

    public function fileExists(string $file): bool
    {
        return $this->driver->isExists($file) && $this->driver->isFile($file);
    }

    public function dirExists(string $path): bool
    {
        return $this->driver->isExists($path) && $this->driver->isDirectory($path);
    }

    public function unlink(string $file): bool
    {
        if ($this->fileExists($file)) {
            return $this->driver->deleteFile($file);
        }

        return true;
    }

    public function mkdir(string $dir, int $mode = 0777): bool
    {
        $result = $this->driver->createDirectory($dir, $mode);

        if ($result && !$this->driver->isWritable($dir)) {
            $this->driver->changePermissions($dir, $mode);
        }

        return $result;
    }

    public function rmdir(string $dir): bool
    {
        if (!$this->dirExists($dir)) {
            return true;
        }

        $result = $this->rmdirRecursive($dir);

        if (!$result) {
            throw new Exception(__("Can't remove folder %s", $dir));
        }

        return $result;
    }

    public function rmdirRecursive(string $dir): bool
    {
        return $this->driver->deleteDirectory($dir);
    }

    public function isWin(): bool
    {
        return strtolower(substr(PHP_OS, 0, 3)) == 'win';
    }

    public function isRemoteStorageEnabled(): bool
    {
        return $this->isRemoteStorageEnabled;
    }

    public function fileGetContents(string $path, ?string $flag = null, $context = null): string
    {
        return $this->driver->fileGetContents($path, $flag, $context);
    }

    public function filePutContents(string $path, string $content, ?string $mode = null): int
    {
        return (int)$this->driver->filePutContents($path, $content, $mode);
    }

    public function isWritable(string $path): bool
    {
        return $this->driver->isWritable($path);
    }

    public function getParentDirectory(string $path): string
    {
        return $this->driver->getParentDirectory($path);
    }

    public function getRealPath(string $file): ?string
    {
        $path = $file;

        if ($this->isRemoteStorageEnabled()) {
            $path = $this->driver->getRealPath($file) ?: null;
        }

        return $path;
    }

    public function fileOpen($path, $mode)
    {
        return $this->driver->fileOpen($path, $mode);
    }

    public function fileClose($resource)
    {
        return $this->driver->fileClose($resource);
    }

    public function fileLock($resource, $lockMode = LOCK_EX)
    {
        return $this->driver->fileLock($resource, $lockMode);
    }

    public function fileUnlock($resource)
    {
        return $this->driver->fileUnlock($resource);
    }

    public function readDirectory($path)
    {
        return $this->driver->readDirectory($path);
    }

    public function isReadable($path)
    {
        return $this->driver->isReadable($path);
    }

    public function fileReadLine($path, $length, $ending = null)
    {
        return $this->driver->fileReadLine($path, $length, $ending);
    }

    public function isFile($path)
    {
        return $this->driver->isFile($path);
    }

    public function stat($path)
    {
        return $this->driver->stat($path);
    }

    public function fileGetCsv($resource, $length = 0, $delimiter = ',', $enclosure = '"')
    {
        return $this->driver->fileGetCsv($resource, $length, $delimiter, $enclosure);
    }

    public function getCsvData(string $filePath): array
    {
        $fileResource = $this->fileOpen($filePath, 'r');

        try {
            $data = [];
            while ($rowData = $this->fileGetCsv($fileResource)) {
                $data[] = $rowData;
            }
        } catch (Exception $e) {
            $this->fileClose($fileResource);
            throw $e;
        }

        $this->fileClose($fileResource);

        return $data;
    }

    public function getCsvItems(string $filePath): array
    {
        $items = [];

        $data = $this->getCsvData($filePath);

        if (count($data) > 1) {
            for ($i = 1; $i < count($data); ++$i) {
                $item = [];
                for ($j = 0; $j < count($data[0]); ++$j) {
                    if (isset($data[$i][$j]) && trim($data[$i][$j]) != '') {
                        $preparedKey = preg_replace('/[[:^print:]]/', "", $data[0][$j]);

                        $item[strtolower($preparedKey)] = trim($data[$i][$j]);
                    }
                }
                $items[] = $item;
            }
        }

        return $items;
    }
}
