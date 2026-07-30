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



namespace Mirasvit\Core\Service;

use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Module\FullModuleList;
use Mirasvit\Core\Model\Package;
use Mirasvit\Core\Model\PackageFactory;

/**
 * @SuppressWarnings(PHPMD)
 */
class PackageService
{
    const PACKAGE_LIST_URL = 'https://files.mirasvit.com/feed/package-list.json';

    private $packageFactory;

    private $fullModuleList;

    private $dirReader;

    private $feedService;

    public function __construct(
        PackageFactory $packageFactory,
        FullModuleList $fullModuleList,
        DirReader      $dirReader,
        FeedService    $feedService
    ) {
        $this->packageFactory = $packageFactory;
        $this->fullModuleList = $fullModuleList;
        $this->dirReader      = $dirReader;
        $this->feedService    = $feedService;
    }

    /** @return Package[] */
    public function getPackageList()
    {
        $packageList = [];

        foreach ($this->fullModuleList->getAll() as $moduleData) {
            if (substr($moduleData['name'], 0, strlen('Mirasvit_')) !== 'Mirasvit_') {
                continue;
            }

            $packageInformation = $this->getPackageInformation($moduleData['name']);
            $versionTxt         = $this->getVersionTxt($moduleData['name']);

            if (!$packageInformation) {
                continue;
            }

            if (!isset($packageInformation['require'])) {
                $packageInformation['require'] = [];
            }

            if (!isset($packageInformation['version'])) {
                $packageInformation['version'] = 'unknown';
            }

            if (!isset($packageInformation['description'])) {
                $packageInformation['description'] = '';
            }

            $packageName = (string)$packageInformation['name'];

            if (isset($packageList[$packageName])) {
                $packageList[$packageName]->addModuleName((string)$moduleData['name']);

                continue;
            }

            $package = $this->packageFactory->create();
            $package
                ->setPackage($packageName)
                ->addModuleName((string)$moduleData['name'])
                ->setVersion((string)$packageInformation['version'])
                ->setVersionTxt((string)$versionTxt)
                ->setRequire((array)array_keys($packageInformation['require']))
                ->setLabel((string)$packageInformation['description']);

            $packageList[$packageName] = $package;
        }

        $externalList = $this->feedService->load(self::PACKAGE_LIST_URL);
        if (!$externalList) {
            $externalList = [];
        }

        foreach ($packageList as $package) {
            foreach ($externalList as $externalData) {
                if (!isset($externalData['package'])) {
                    continue;
                }

                $externalData = array_merge([
                    'package'       => '',
                    'version'       => '',
                    'sku'           => '',
                    'title'         => '',
                    'url'           => '',
                    'docs_url'      => '',
                    'changelog_url' => '',
                ], $externalData);

                if ($externalData['package'] !== $package->getPackage()) {
                    continue;
                }

                $package->setLatestVersion((string)$externalData['version'])
                    ->setSku((string)$externalData['sku'])
                    ->setUrl((string)$externalData['url'])
                    ->setDocsUrl((string)$externalData['docs_url'])
                    ->setChangelogUrl((string)$externalData['changelog_url']);;

                if ((string)$externalData['title']) {
                    $package->setLabel((string)$externalData['title']);
                }
            }
        }

        return array_values($packageList);
    }

    public function getPackage(string $moduleName): ?Package
    {
        foreach ($this->getPackageList() as $package) {
            if ($package->getPackage() == $moduleName) {
                return $package;
            }

            foreach ($package->getModuleList() as $module) {
                if ($module == $moduleName) {
                    return $package;
                }
            }
        }

        return null;
    }

    public function getPackageInformation(string $moduleName): ?array
    {
        try {
            $dir = $this->dirReader->getModuleDir("", $moduleName);
        } catch (\Exception $e) {
            return null;
        }

        if (file_exists($dir . '/version.json')) {
            $data = SerializeService::decode(file_get_contents($dir . '/version.json'));

            return [
                'name'    => $data['package_name'] ?? '',
                'version' => $data['version'] ?? '',
            ];
        }

        if (file_exists($dir . '/composer.json')) {
            $data = SerializeService::decode(file_get_contents($dir . '/composer.json'));

            return [
                'name'    => $data['name'] ?? '',
                'version' => $data['version'] ?? '',
            ];
        }

        if (file_exists($dir . '/../../composer.json')) {
            $data = SerializeService::decode(file_get_contents($dir . '/../../composer.json'));

            return [
                'name'    => $data['name'] ?? '',
                'version' => $data['version'] ?? '',
            ];
        }

        return null;
    }

    /**
     * @param string $moduleName
     *
     * @return string|null
     */
    private function getVersionTxt($moduleName)
    {
        try {
            $dir = $this->dirReader->getModuleDir("", $moduleName);
        } catch (\Exception $e) {
            return null;
        }

        if (file_exists($dir . '/version.txt')) {
            return file_get_contents($dir . '/version.txt');
        }

        return null;
    }
}
