<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model;

use Psr\Log\LoggerInterface;
use Secomm\CodRisk\Api\RiskEventRecorderInterface;
use Secomm\CodRisk\Api\Data\RiskEventInterface;
use Secomm\CodRisk\Model\Audit\AuditWriter;
use Secomm\CodRisk\Model\Reason\ReasonCatalog;

/**
 * Persists normalized risk events with the include flag snapshotted at record
 * time (spec nguồn §13, CR: reason snapshot).
 */
class RiskEventRecorder implements RiskEventRecorderInterface
{
    public function __construct(
        private readonly CodRiskEventFactory $eventFactory,
        private readonly ReasonCatalog $reasonCatalog,
        private readonly AuditWriter $auditWriter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(RiskEventInterface $event): void
    {
        $reasonCode = $event->getReasonCode();
        $known = isset($this->reasonCatalog->getReasons()[$reasonCode]);
        $includeSnapshot = $known
            ? $this->reasonCatalog->isIncludedInCount($reasonCode, $event->getWebsiteId())
            : false;

        if (!$known) {
            // Unknown reason codes are stored for audit but never counted (fail-safe).
            $this->logger->warning(
                sprintf('[CodRisk] Unknown risk reason code "%s" recorded, excluded from historical count.', $reasonCode)
            );
        }

        $model = $this->eventFactory->create();
        $model->setData([
            'normalized_phone' => $event->getNormalizedPhone(),
            'website_id' => (int)($event->getWebsiteId() ?? 0),
            'order_id' => $event->getOrderId(),
            'quote_id' => $event->getQuoteId(),
            'customer_id' => $event->getCustomerId(),
            'reason_code' => $reasonCode,
            'source' => $event->getSource(),
            'include_snapshot' => $includeSnapshot ? 1 : 0,
            'note' => $event->getNote(),
        ]);
        $model->save();

        $this->auditWriter->log(
            Audit\AuditLog::ENTITY_TYPE_EVENT,
            (int)$model->getId(),
            'recorded',
            null,
            sprintf('%s by %s (include=%s)', $reasonCode, $event->getSource(), $includeSnapshot ? 'yes' : 'no'),
            (string)$event->getNote()
        );
    }
}
