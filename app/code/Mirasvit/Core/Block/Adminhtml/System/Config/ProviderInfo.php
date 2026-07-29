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

namespace Mirasvit\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Mirasvit\Core\Ai\Model\ConfigProvider as CoreConfigProvider;

class ProviderInfo extends Template
{
    protected $_template = 'Mirasvit_Core::system/config/provider_info_content.phtml';

    private $coreConfigProvider;

    public function __construct(
        Context            $context,
        CoreConfigProvider $coreConfigProvider,
        array              $data = []
    ) {
        parent::__construct($context, $data);
        $this->coreConfigProvider = $coreConfigProvider;
    }

    public function getProviderInfo(): array
    {
        $providers = [];

        foreach (CoreConfigProvider::AVAILABLE_PROVIDERS as $providerCode) {
            $providers[$providerCode] = [
                'name'    => $this->coreConfigProvider->getProviderLabel($providerCode),
                'enabled' => $this->coreConfigProvider->isProviderEnabled($providerCode),
            ];
        }

        return $providers;
    }

    public function getEnabledCount(): int
    {
        $providers = $this->getProviderInfo();

        return array_sum(array_column($providers, 'enabled'));
    }

    public function getTotalCount(): int
    {
        return count($this->getProviderInfo());
    }

    public function getCoreSettingsLink(): string
    {
        return $this->_urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'mst_core']);
    }
}
