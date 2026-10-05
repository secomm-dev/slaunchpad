<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineRecordingState;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the generic offline decision seam, validated BEFORE the
 * native shipment save commits (`sales_order_shipment_save_before`): a save carrying the
 * offline intent (`shipment[fulfillment_mode]=OFFLINE`) is only accepted for a FRESH shipment
 * on a carrier with an ENABLED offline capability. Anything else is rejected fail-closed —
 * the core save controller renders the LocalizedException, the DB\Transaction never runs, and
 * nothing is written (no shipment, no metadata, no comment).
 *
 * On acceptance, the durable history comment is added here so it persists ATOMICALLY with the
 * main sales transaction (Shipment\Relation) — the commit_after sibling only records metadata,
 * never re-saves for presentation.
 *
 * Offline-eligible CARRIER RULES stay in the carrier: this observer never validates packages —
 * an offline save records facts (possibly constraint-violating) without any provider submission.
 */
class ShipmentOfflineIntentValidationObserver implements ObserverInterface
{
    public function __construct(
        private readonly FulfillmentModeResolver $resolver,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getData('shipment');
        if (!$shipment instanceof Shipment || !$this->resolver->isOfflineIntent()) {
            return;
        }

        $shipmentId = (int) $shipment->getEntityId();
        if ($shipmentId > 0) {
            if (OfflineRecordingState::isRecording($shipmentId)) {
                // The commit_after recorder's own persistence (metadata + package facts) — a
                // re-save that carries the request intent but is not a new operator action.
                return;
            }
            // A crafted intent on an existing (possibly provider-live) shipment must never flip
            // it to OFFLINE — and must never duplicate the history comment.
            throw new LocalizedException(
                __('Offline fulfillment can only be set while creating the shipment.')
            );
        }

        $carrierCode = $this->resolver->resolveCarrierCode(
            (string) ($shipment->getOrder()?->getShippingMethod() ?? '')
        );
        if ($carrierCode === null) {
            throw new LocalizedException(
                __('Offline shipment creation is not available for this shipping method.')
            );
        }

        $shipment->addComment($this->historyComment($carrierCode));
    }

    private function historyComment(string $carrierCode): \Magento\Framework\Phrase
    {
        $reasonCode = $this->sanitize((string) $this->posted('offline_reason_code'), 64);

        return $reasonCode === ''
            ? __('Offline shipment created — no %1 order was requested; fulfillment is manual.', $carrierCode)
            : __(
                'Offline shipment created — no %1 order was requested (reason: %2); fulfillment is manual.',
                $carrierCode,
                $reasonCode
            );
    }

    /**
     * Scalar client-provided field, presented to admins — keep it short and free of markup.
     */
    private function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(trim(strip_tags($value)), 0, $maxLength);
    }

    private function posted(string $key): string
    {
        $posted = $this->request->getParam('shipment');

        return is_array($posted) && isset($posted[$key]) ? (string) $posted[$key] : '';
    }
}
