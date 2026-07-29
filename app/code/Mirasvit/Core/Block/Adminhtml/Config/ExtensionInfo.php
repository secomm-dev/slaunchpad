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

namespace Mirasvit\Core\Block\Adminhtml\Config;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Mirasvit\Core\Model\Package;
use Mirasvit\Core\Service\FeatureService;
use Mirasvit\Core\Service\GeoLocationModuleRegistry;
use Mirasvit\Core\Service\GeoLocationService;
use Mirasvit\Core\Service\PackageService;

class ExtensionInfo extends Template implements RendererInterface
{
    protected $_template  = 'Mirasvit_Core::config/extension-info.phtml';

    private   $moduleName = '';

    private   $packageService;

    private   $featureService;

    private   $geoLocationService;

    private   $geoLocationModuleRegistry;

    public function __construct(
        PackageService            $packageService,
        FeatureService            $featureService,
        GeoLocationService        $geoLocationService,
        GeoLocationModuleRegistry $geoLocationModuleRegistry,
        Template\Context          $context,
        array                     $data = []
    ) {
        $this->packageService            = $packageService;
        $this->featureService            = $featureService;
        $this->geoLocationService        = $geoLocationService;
        $this->geoLocationModuleRegistry = $geoLocationModuleRegistry;

        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        $this->moduleName = (string)$element->getDataByPath('group/module_name');
        if (!$this->moduleName) {
            return '';
        }

        return (string)$this->toHtml();
    }

    public function getPackage(): ?Package
    {
        return $this->packageService->getPackage($this->moduleName);
    }

    public function getRequestUrl(): string
    {
        return $this->featureService->getFeatureRequestUrl($this->moduleName);
    }

    public function usesGeoLocation(): bool
    {
        return $this->geoLocationModuleRegistry->isModuleRegistered($this->moduleName);
    }

    public function isGeoLocationAvailable(): bool
    {
        return $this->geoLocationService->isAvailable();
    }

    public function isGeoLocationAutoUpdateEnabled(): bool
    {
        return $this->geoLocationService->isAutoUpdateEnabled();
    }

    public function getGeoLocationStatus(): string
    {
        return $this->geoLocationService->getStatus();
    }

    public function getGeoLocationConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'mst_core']);
    }
}