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
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);


namespace Mirasvit\SeoAi\Cron;


use Magento\Framework\App\MaintenanceMode;
use Mirasvit\SeoAi\Model\ConfigProvider;
use Mirasvit\SeoAi\Service\MetaAutoFixService;
use Psr\Log\LoggerInterface;

class MetaAutoFixCron
{
    private $configProvider;

    private $service;

    private $maintenanceMode;

    private $logger;

    public function __construct(
        ConfigProvider $configProvider,
        MetaAutoFixService $service,
        MaintenanceMode $maintenanceMode,
        LoggerInterface $logger
    ) {
        $this->configProvider  = $configProvider;
        $this->service         = $service;
        $this->maintenanceMode = $maintenanceMode;
        $this->logger          = $logger;
    }

    public function execute(): void
    {
        if (!$this->configProvider->isCronEnabled()) {
            return;
        }

        if ($this->maintenanceMode->isOn()) {
            $this->logger->notice('Maintnance mode enabled. SEO audit will not perform checks');

            return;
        }

        $this->service->processUrls(1000, 20 * 60);
    }
}
