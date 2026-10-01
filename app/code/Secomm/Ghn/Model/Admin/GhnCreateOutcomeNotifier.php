<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Admin;

use Magento\Framework\Message\ManagerInterface;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnCreateReasonLabel;

/**
 * TASK-W5BW4F — layer 2 of the create-failure surfacing contract (DEC-TASKW5BW4F-001): a
 * failed GHN create must NEVER be silent. The create observer feeds every non-SUCCESS outcome
 * through this notifier, which puts an admin error message on the save redirect (the attempt
 * runs synchronously in the same request) and points at the retry CLI.
 *
 * Boundaries mirror {@see GhnActionOutcomeNotifier}: presentation ONLY — never calls the
 * provider, never reconciles, never mutates order/shipment state. No raw provider payload /
 * token / PII is ever emitted.
 */
class GhnCreateOutcomeNotifier
{
    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly GhnCreateReasonLabel $reasonLabel
    ) {
    }

    public function notifyFailure(GhnCreateOutcome $outcome, int $shipmentId): void
    {
        $detail = $outcome->getStatus() === GhnCreateOutcome::STATUS_COD_REJECTED
            ? (string) $outcome->getRejectionMessage()
            : $this->reasonLabel->label($outcome->getReason());

        $this->messageManager->addErrorMessage(
            __(
                'GHN shipment create %1: %2 — reconcile via "bin/magento secomm:ghn:shipment:retry %3".',
                $outcome->getStatus(),
                $detail,
                (string) $shipmentId
            )
        );
    }

    /**
     * Durable shipment-comment text for a non-SUCCESS create outcome (UNAVAILABLE /
     * TECHNICAL_FAILURE / UNKNOWN-shaped rows; COD_REJECTED comments its own message).
     */
    public function failureComment(GhnCreateOutcome $outcome): \Magento\Framework\Phrase
    {
        return __(
            'GHN shipment create failed (%1): %2',
            $outcome->getStatus(),
            $this->reasonLabel->label($outcome->getReason())
        );
    }
}
