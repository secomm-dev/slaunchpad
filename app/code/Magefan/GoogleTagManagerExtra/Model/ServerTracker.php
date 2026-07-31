<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleTagManagerExtra\Model;

use Psr\Log\LoggerInterface;
use Magefan\GoogleTagManagerExtra\Api\ServerTrackerInterface;
use Magefan\GoogleTagManagerExtra\Api\ServerTracker\TrackerInterface;
use Magefan\GoogleTagManagerExtra\Model\Config as ConfigExtra;
use Magefan\GeoIp\Api\IpToCountryRepositoryInterface;

class ServerTracker implements ServerTrackerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $trackerPool;

    /**
     * @var Config
     */
    private $configExtra;

    /**
     * @var IpToCountryRepositoryInterface
     */
    private $ipToCountryRepository;

    /**
     * @param LoggerInterface $logger
     * @param ConfigExtra $configExtra
     * @param IpToCountryRepositoryInterface $ipToCountryRepository
     * @param array $trackerPool
     */
    public function __construct(
        LoggerInterface $logger,
        ConfigExtra $configExtra,
        IpToCountryRepositoryInterface $ipToCountryRepository,
        array $trackerPool
    ) {
        $this->logger = $logger;
        $this->configExtra = $configExtra;
        $this->ipToCountryRepository = $ipToCountryRepository;
        $this->trackerPool = $trackerPool;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function push(array $data): bool
    {
        $ignoreIps = $this->configExtra->getGtmServerIgnoreIps();
        if ($ignoreIps && in_array($this->ipToCountryRepository->getRemoteAddress(), $ignoreIps)) {
            return true;
        }

        $pushed = false;
        if (empty($data)) {
            return $pushed;
        }

        foreach ($this->trackerPool as $tracker) {
            if ($tracker instanceof TrackerInterface) {
                if (!$tracker->isEnabled()) {
                    continue;
                }
                try {
                    $result = $tracker->push($data);
                    if ($result) {
                        $pushed = true;
                    }
                } catch (\Exception $e) {
                    $this->logger->critical('GTM Server Push Error - ' . get_class($tracker) . ': '. $e->getMessage());
                }
            }
        }

        return $pushed;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        foreach ($this->trackerPool as $tracker) {
            if ($tracker instanceof TrackerInterface) {
                if ($tracker->isEnabled()) {
                    return true;
                }
            }
        }

        return false;
    }
}
