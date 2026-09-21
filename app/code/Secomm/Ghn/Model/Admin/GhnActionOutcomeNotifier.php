<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Admin;

use Magento\Framework\Message\ManagerInterface;
use Secomm\Ghn\Model\Shipment\GhnActionOutcome;

/**
 * TASK-PWHG0V pre-review fix pack — maps a normalized {@see GhnActionOutcome} to Magento admin
 * messages (brief §19 semantics, shared by the Cancel and Return controllers — the ONLY
 * duplication the two controllers had).
 *
 * Boundaries: presentation ONLY — the notifier never calls the provider, never reconciles,
 * never mutates order/shipment state, never resolves shipments, never owns lifecycle state.
 * No raw provider payload / token / PII is ever emitted (§12/§37).
 */
class GhnActionOutcomeNotifier
{
    public function __construct(
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function notify(GhnActionOutcome $outcome): void
    {
        switch ($outcome->getStatus()) {
            case GhnActionOutcome::STATUS_SUCCESS:
                $this->messageManager->addSuccessMessage($this->successMessage($outcome));
                break;
            case GhnActionOutcome::STATUS_BUSINESS_REJECTED:
                $this->messageManager->addErrorMessage(
                    __('GHN rejected the %1: %2', $this->actionNoun($outcome), (string) $outcome->getMessage())
                );
                break;
            case GhnActionOutcome::STATUS_TECHNICAL_FAILURE:
                $this->messageManager->addErrorMessage(
                    __('GHN is temporarily unavailable. No automatic retry was performed.')
                );
                break;
            case GhnActionOutcome::STATUS_UNKNOWN_RESULT:
            default:
                $this->messageManager->addNoticeMessage(
                    __('The request may have been processed by GHN. Please refresh/reconcile the tracking status before retrying.')
                );
                break;
        }
    }

    private function successMessage(GhnActionOutcome $outcome): \Magento\Framework\Phrase
    {
        return $outcome->getAction() === GhnActionOutcome::ACTION_RETURN
            ? __('GHN return request accepted. Order %1.', $outcome->getProviderOrderCode())
            : __('GHN shipment cancellation accepted. Order %1.', $outcome->getProviderOrderCode());
    }

    private function actionNoun(GhnActionOutcome $outcome): \Magento\Framework\Phrase
    {
        return $outcome->getAction() === GhnActionOutcome::ACTION_RETURN
            ? __('return request')
            : __('cancellation');
    }
}
