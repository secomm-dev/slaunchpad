<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Shipment;
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Shipment\GhnCreateParcelValidator;
use Secomm\Ghn\Model\Shipment\GhnCreateValidationException;
use Secomm\Ghn\Model\Shipment\PostedPhysicalPackages;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineEligibilitySession;

/**
 * TASK-W5BW4F — layer 1 of the create-failure surfacing contract (DEC-TASKW5BW4F-001): a FRESH
 * shipment save whose confirmed packages would deterministically fail the GHN create
 * (missing/empty rows, per-package weight or per-dimension limits → INVALID_PARCEL) is BLOCKED
 * here, before the shipment exists. The core shipping save controller catches the
 * {@see GhnCreateValidationException} (a LocalizedException) and renders its message to the
 * admin — no shipment row, no PENDING anchor, no doomed physical snapshot, no silent FAILED
 * state that a retry could only replay.
 *
 * Deliberately narrow:
 * - fresh saves only (entity id is assigned at insert) — re-saves of an existing shipment
 *   (comments, tracks, the snapshot re-save) must never be blocked by a stale snapshot; those
 *   create attempts are post-commit and surface loudly (layer 2, GhnShipmentCreateObserver);
 * - same carrier gate as the create trigger (raw method prefix — Order::getShippingMethod(true)
 *   would split secomm_ghn_secomm_ghn wrong), and the SAME validator instance contract as the
 *   creation service, so the two gates cannot drift apart;
 * - this observer throws BY CONTRACT (unlike the create observer) — validation happens before
 *   any write, so there is nothing to contain.
 *
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the offline escape: a save carrying the generic offline
 * intent is NOT a provider submission, so this gate stands down (the generic capability check
 * lives in the ShippingCore save_before observer); a block with an OFFLINE-ELIGIBLE token
 * stashes the structured eligibility (order-scoped session, ShippingCore-owned) so the
 * re-rendered form can prefill the offline reason, and the message points at the offline path.
 */
class GhnShipmentSaveValidationObserver implements ObserverInterface
{
    /** Reason tokens whose deterministic failure legitimately opens the offline path (P1, frozen in DEC). */
    private const OFFLINE_ELIGIBLE_TOKENS = [
        GhnCreateValidationException::REASON_INVALID_PARCEL,
        GhnCreateValidationException::REASON_INVALID_CONFIGURATION,
    ];

    public function __construct(
        private readonly GhnCreateParcelValidator $validator,
        private readonly HttpRequest $request,
        private readonly GhnLogger $logger,
        private readonly FulfillmentModeResolver $fulfillmentModeResolver,
        private readonly OfflineEligibilitySession $eligibilitySession
    ) {
    }

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getData('shipment');
        if (!$shipment instanceof Shipment || (int) $shipment->getEntityId() > 0) {
            return;
        }

        $shippingMethod = (string) ($shipment->getOrder()?->getShippingMethod() ?? '');
        if (!str_starts_with($shippingMethod, Ghn::CARRIER_CODE . '_')) {
            return;
        }

        if ($this->fulfillmentModeResolver->isOfflineIntent()) {
            return; // offline save: facts are recorded without submitting anything to GHN
        }

        $postedRows = PostedPhysicalPackages::fromRequest($this->request);
        try {
            if ($postedRows === null) {
                // A fresh GHN save with no confirmed package information fails closed with the
                // same canonical message the create service uses (no snapshot can exist yet).
                throw new GhnCreateValidationException(
                    GhnCreateValidationException::REASON_INVALID_PARCEL,
                    GhnCreateParcelValidator::missingParcelMessage()
                );
            }

            $this->validator->assertValid(
                $this->validator->fromPostedRows(
                    $postedRows,
                    $shipment->getStoreId() !== null ? (int) $shipment->getStoreId() : null
                )
            );
        } catch (GhnCreateValidationException $validation) {
            // The RENDERED message is the admin-facing contract (getRawMessage keeps the %N
            // placeholders of the source phrase — useless for a stash/prefill or a re-render).
            $renderedMessage = (string) $validation->getMessage();
            $offlineEligible = in_array($validation->getReasonToken(), self::OFFLINE_ELIGIBLE_TOKENS, true);
            if ($offlineEligible) {
                $this->eligibilitySession->stash(
                    (int) ($shipment->getOrder()?->getEntityId() ?? 0),
                    $validation->getReasonToken(),
                    $renderedMessage
                );
            }

            $this->logger->call('GHN shipment save blocked by parcel validation.', [
                'reason' => $validation->getReasonToken(),
                'message' => $validation->getRawMessage(),
            ]);

            throw $offlineEligible
                ? new GhnCreateValidationException(
                    $validation->getReasonToken(),
                    __('%1 Use "Create Offline Shipment" to record the shipment without a GHN order.', $renderedMessage)
                )
                : $validation;
        }
    }
}
