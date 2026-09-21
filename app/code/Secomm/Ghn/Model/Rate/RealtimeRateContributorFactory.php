<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-WAWNDS freeze — creates the per-request realtime contributor the composition layer
 * places into `CarrierRateExecutionRequest::getRealtimeContributor()`. Each collectRates()
 * invocation closes its CURRENT RateRequest into a fresh contributor instance (the shared
 * execution seam must stay provider-free, so Magento rate internals travel by closure).
 */
class RealtimeRateContributorFactory
{
    public function __construct(
        private readonly GhnRateCalculator $rateCalculator,
        private readonly GhnRateRequestMapper $requestMapper,
        private readonly GhnLogger $logger
    ) {
    }

    public function create(RateRequest $request): RealtimeRateContributor
    {
        return new RealtimeRateContributor(
            $this->rateCalculator,
            $this->requestMapper,
            $this->logger,
            $request
        );
    }
}
