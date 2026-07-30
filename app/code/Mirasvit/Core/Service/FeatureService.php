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

class FeatureService
{
    private $packageService;

    public function __construct(
        PackageService $packageService
    ) {
        $this->packageService = $packageService;
    }

    public function getImprovementSuggestionUrl(string $moduleName): string
    {
        $moduleName = $this->getModuleName($moduleName);

        return 'https://mirasvit.com/request-a-feature.html?subject=Improve Suggestion for ' . $moduleName;
    }

    public function getFeatureRequestUrl(string $moduleName): string
    {
        $moduleName = $this->getModuleName($moduleName);

        return 'https://mirasvit.com/request-a-feature.html?subject=Feature Request for ' . $moduleName;
    }

    private function getModuleName(string $moduleName): string
    {
        $package = $this->packageService->getPackage($moduleName);
        if (!$package) {
            return $moduleName;
        }

        return $package->getLabel();
    }
}