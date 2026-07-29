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

namespace Mirasvit\Core\Setup\Patch\Data;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallGeoLocationDatabase implements DataPatchInterface
{
    private const DATABASE_FILENAME = 'GeoLite2-City.mmdb';
    private const HASH_FILENAME     = 'GeoLite2-City.mmdb.sha256';
    private const DATABASE_DIR      = 'mirasvit/geolocation';

    private $setup;

    private $filesystem;

    private $fileDriver;

    private $componentRegistrar;

    public function __construct(
        ModuleDataSetupInterface    $setup,
        Filesystem                  $filesystem,
        FileDriver                  $fileDriver,
        ComponentRegistrarInterface $componentRegistrar
    ) {
        $this->setup              = $setup;
        $this->filesystem         = $filesystem;
        $this->fileDriver         = $fileDriver;
        $this->componentRegistrar = $componentRegistrar;
    }

    public function apply(): DataPatchInterface
    {
        $this->setup->getConnection()->startSetup();

        $targetPath = $this->getTargetPath(self::DATABASE_FILENAME);

        if (!$this->fileDriver->isExists($targetPath)) {
            $sourcePath = $this->getBundledPath(self::DATABASE_FILENAME);

            if ($this->fileDriver->isExists($sourcePath)) {
                $targetDir = $this->fileDriver->getParentDirectory($targetPath);

                if (!$this->fileDriver->isDirectory($targetDir)) {
                    $this->fileDriver->createDirectory($targetDir, 0755);
                }

                $this->fileDriver->copy($sourcePath, $targetPath);

                // Also copy hash file for version info
                $sourceHashPath = $this->getBundledPath(self::HASH_FILENAME);
                $targetHashPath = $this->getTargetPath(self::HASH_FILENAME);

                if ($this->fileDriver->isExists($sourceHashPath)) {
                    $this->fileDriver->copy($sourceHashPath, $targetHashPath);
                }
            }
        }

        $this->setup->getConnection()->endSetup();

        return $this;
    }

    private function getBundledPath(string $filename): string
    {
        $modulePath = $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'Mirasvit_Core'
        );

        return $modulePath . '/Setup/data/' . $filename;
    }

    private function getTargetPath(string $filename): string
    {
        $varDir = $this->filesystem
            ->getDirectoryRead(DirectoryList::VAR_DIR)
            ->getAbsolutePath();

        return $varDir . self::DATABASE_DIR . '/' . $filename;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
