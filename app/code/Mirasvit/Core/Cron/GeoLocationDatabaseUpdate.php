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

namespace Mirasvit\Core\Cron;

use Mirasvit\Core\Service\GeoLocationService;
use Psr\Log\LoggerInterface;

class GeoLocationDatabaseUpdate
{
    private $geoLocationService;

    private $logger;


    public function __construct(
        GeoLocationService $geoLocationService,
        LoggerInterface    $logger
    ) {
        $this->geoLocationService = $geoLocationService;
        $this->logger             = $logger;
    }

    public function execute(): void
    {
        if (!$this->geoLocationService->isConfigured()) {
            return;
        }

        $result = $this->geoLocationService->downloadDatabase();

        if ($result['downloaded']) {
            $this->logger->info('GeoLocation database updated: ' . $result['message']);
        }
    }
}
