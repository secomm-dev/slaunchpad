<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderAddressInterface;

/**
 * TASK-9Q5ZAK (GHN-D) — pure Create Order payload builder (contract source:
 * `.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md` §5/§15, docs 2026-09-11 + sandbox
 * L8TL6B). Exactly one destination representation per DEC-FEATFQWEQ3-001:
 *
 *   is_new_to_address = true, to_province_name / to_ward_name = GHN_ADMIN_2025 verbatim names,
 *   to_district_name = "" — NEVER to_district_id / to_ward_code (AC-SHIP-001..003).
 *
 * Absent BY DESIGN (each absence is an architecture boundary, not an omission):
 *   cod_amount/insurance_value/order_value — upstream owns collection/insurance policy (GHN-D
 *     boundary: no collection amount supplied upstream → provider defaults 0; §13/§16);
 *   content — REQUIRED by the contract when items[] is absent: item-name summary ≤2000 chars;
 *   from_* / return_* — GHN ShopId profile supplies sender/return defaults (§17/§18);
 *   service_id — dead provider identity, never sent, never persisted.
 *
 * Config values are validated here (fail-closed INVALID_CONFIGURATION) so a misconfigured
 * merchant never produces a request GHN would interpret differently.
 */
class GhnCreateRequestBuilder
{
    /** payment_type_id enum: 1 = shop pays the GHN fee, 2 = buyer pays (unrelated to COD). */
    private const PAYMENT_TYPES = [1, 2];

    /** required_note enum per the current contract — config-driven, never hardcoded to one fashion value. */
    private const REQUIRED_NOTES = ['KHONGCHOXEMHANG', 'CHOXEMHANGKHONGTHU', 'CHOTHUHANG'];

    /** GHN content field limit. */
    private const MAX_CONTENT_LENGTH = 2000;

    public function build(
        string $clientOrderCode,
        string $provinceName,
        string $wardName,
        GhnParcelPlan $plan,
        OrderAddressInterface $address,
        int $paymentTypeId,
        string $requiredNote,
        string $content
    ): array {
        if (!in_array($paymentTypeId, self::PAYMENT_TYPES, true)) {
            throw new GhnCreateValidationException(
                GhnCreateValidationException::REASON_INVALID_CONFIGURATION,
                __('GHN create: payment_type must be 1 (shop pays) or 2 (buyer pays), got "%1".', (string) $paymentTypeId)
            );
        }
        if (!in_array($requiredNote, self::REQUIRED_NOTES, true)) {
            throw new GhnCreateValidationException(
                GhnCreateValidationException::REASON_INVALID_CONFIGURATION,
                __('GHN create: required_note must be one of %1, got "%2".', implode(', ', self::REQUIRED_NOTES), $requiredNote)
            );
        }

        $toName = $this->resolveRecipientName($address);
        $toPhone = trim((string) $address->getTelephone());
        $toAddress = $this->resolveStreetText($address);
        if ($toName === '' || $toPhone === '' || $toAddress === '') {
            throw new GhnCreateValidationException(
                GhnCreateValidationException::REASON_INVALID_PARCEL,
                __('GHN create: recipient name, phone and street are all required by the provider.')
            );
        }

        // Type 2: root weight/dims = the single physical package; content contract-required (no
        // items). Type 5 (r3, sandbox-verified): root `weight` (= the factual Σ) is PROVIDER-
        // MANDATORY; root length/width/height are NOT required and are OMITTED — items[] carries
        // the physical truth per package; content is unnecessary when items[] is present.
        $payload = [
            'client_order_code' => $clientOrderCode,
            'to_name' => $toName,
            'to_phone' => $toPhone,
            'to_address' => $toAddress,
            'to_province_name' => $provinceName,
            'to_ward_name' => $wardName,
            'to_district_name' => '',
            'is_new_to_address' => true,
            'service_type_id' => $plan->getServiceTypeId(),
            'payment_type_id' => $paymentTypeId,
            'required_note' => $requiredNote,
        ];

        if ($plan->getItems() !== null) {
            $payload['weight'] = (int) $plan->getRootWeightG();
            $payload['items'] = $plan->getItems();
        } else {
            $payload['weight'] = (int) $plan->getRootWeightG();
            $payload['length'] = (int) $plan->getRootLengthCm();
            $payload['width'] = (int) $plan->getRootWidthCm();
            $payload['height'] = (int) $plan->getRootHeightCm();
            $payload['content'] = mb_substr($content, 0, self::MAX_CONTENT_LENGTH);
        }

        return $payload;
    }

    private function resolveRecipientName(OrderAddressInterface $address): string
    {
        return trim(trim((string) $address->getFirstname()) . ' ' . trim((string) $address->getLastname()));
    }

    /**
     * @return string comma-joined non-empty street lines (no recipient PII beyond the shipped-to address itself)
     */
    private function resolveStreetText(OrderAddressInterface $address): string
    {
        $lines = array_filter(
            (array) $address->getStreet(),
            static fn ($line): bool => is_string($line) && trim($line) !== ''
        );

        return implode(', ', array_map('trim', array_values($lines)));
    }
}
