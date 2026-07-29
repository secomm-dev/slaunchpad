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

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Mirasvit\Core\Service\GeoLocationModuleRegistry;
use Mirasvit\Core\Service\GeoLocationService;

class GeoLocationField extends Field
{
    private $geoLocationService;

    private $moduleRegistry;

    public function __construct(
        Context                   $context,
        GeoLocationService        $geoLocationService,
        GeoLocationModuleRegistry $moduleRegistry,
        array                     $data = []
    ) {
        $this->geoLocationService = $geoLocationService;
        $this->moduleRegistry     = $moduleRegistry;

        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    public function isConfigured(): bool
    {
        return $this->geoLocationService->isConfigured();
    }

    public function isAvailable(): bool
    {
        return $this->geoLocationService->isAvailable();
    }

    public function hasDatabaseFile(): bool
    {
        return $this->geoLocationService->hasDatabaseFile();
    }

    public function isAutoUpdateEnabled(): bool
    {
        return $this->geoLocationService->isAutoUpdateEnabled();
    }

    public function getStatus(): string
    {
        return $this->geoLocationService->getStatus();
    }

    /**
     * @return array|null
     */
    public function getDatabaseInfo(): ?array
    {
        return $this->geoLocationService->getDatabaseInfo();
    }

    public function getDownloadUrl(): string
    {
        return $this->getUrl('mstcore/geolocation/download');
    }

    public function formatVersion(?string $version): string
    {
        if (!$version || strlen($version) !== 8) {
            return $version ?? '';
        }

        // Convert 20260120 to 2026-01-20
        return substr($version, 0, 4) . '-' . substr($version, 4, 2) . '-' . substr($version, 6, 2);
    }

    /**
     * @return array<string, string>
     */
    public function getEnabledConsumers(): array
    {
        return $this->moduleRegistry->getEnabledModules();
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        if (!$this->getTemplate()) {
            $this->setTemplate('Mirasvit_Core::config/geolocation-field.phtml');
        }

        return $this;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
}
