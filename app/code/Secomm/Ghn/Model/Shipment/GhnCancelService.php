<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Magento\Sales\Model\Order\Shipment;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidAddressException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateLimitException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Client\GhnEndpoints;
use Secomm\Ghn\Model\Logger\GhnLogger;

/**
 * TASK-4ATBC4 (GHN-E2) — requests provider CANCELLATION of an existing GHN order.
 *
 * Contract (matrix §9, docs 2026-09-11 + sandbox-proven): POST `v2/switch-status/cancel` with
 * `{order_codes: [...], reason_code, reason?}`; HTTP 200 carries PER-ORDER results — `result`
 * false means refused (already delivered/cancelled, invalid state) and is NEVER a success.
 * Batch all-or-nothing is a transport detail: this service always sends exactly one code
 * (cancelOne semantics), so the batch either fully applies or fully rejects.
 *
 * Boundaries: executes the provider action ONLY — Magento order business state, refunds,
 * inventory and notifications are upstream concerns. The action is a MUTATION: no automatic
 * retry; an uncertain result yields UNKNOWN_RESULT and is reconciled through the E1 detail
 * API before any repeated action (sandbox: provider dedupes by order identity).
 *
 * TASK-GKHXY1 r2 uncertainty taxonomy for mutations: transport-level ambiguity (timeout,
 * connection error) and provider 5xx both leave applied-vs-not-applied open — the HTTP client
 * cannot prove a before-send failure, so the conservative fallback is UNKNOWN_RESULT. A 429
 * throttle is definitive not-applied evidence (rejected before processing) → TECHNICAL_FAILURE.
 * HTTP 200 with an unusable body is post-submission uncertainty → UNKNOWN_RESULT.
 *
 * Provider reason codes (config-free, caller-supplied): GHN-CO001 (pickup overdue),
 * GHN-CO002 (out of stock), GHN-CO003 (customer cancelled), GHN-CANCEL-OTHER.
 */
class GhnCancelService
{
    private const OPERATION = 'cancel_order';

    public const ACTION_CANCEL = 'cancel';

    /** Provider-accepted cancellation reason codes (current contract enum). */
    public const REASON_CODES = ['GHN-CO001', 'GHN-CO002', 'GHN-CO003', 'GHN-CANCEL-OTHER'];

    public function __construct(
        private readonly GhnApiClientInterface $apiClient,
        private readonly GhnShipmentRepository $shipmentRepository,
        private readonly GhnLogger $logger
    ) {
    }

    /**
     * @param string $reasonCode one of self::REASON_CODES (fail-closed otherwise)
     * @param string $reason     optional free-text detail
     */
    public function cancel(Shipment $shipment, string $reasonCode, string $reason = ''): GhnActionOutcome
    {
        $shipmentId = (int) $shipment->getEntityId();
        $row = $this->shipmentRepository->findByShipmentId($shipmentId);

        if ($row === null
            || ($row['provider_status'] ?? '') !== GhnShipmentRepository::STATUS_SUBMITTED
            || empty($row['ghn_order_code'])
        ) {
            return GhnActionOutcome::businessRejected(
                self::ACTION_CANCEL,
                (string) ($row['ghn_order_code'] ?? ''),
                'NO_PROVIDER_ORDER',
                'No SUBMITTED GHN order exists for this shipment.'
            );
        }

        $orderCode = (string) $row['ghn_order_code'];
        if (!in_array($reasonCode, self::REASON_CODES, true)) {
            return GhnActionOutcome::businessRejected(
                self::ACTION_CANCEL,
                $orderCode,
                'INVALID_REASON_CODE',
                'Cancellation reason must be one of: ' . implode(', ', self::REASON_CODES) . '.'
            );
        }

        $payload = [
            'order_codes' => [$orderCode],
            'reason_code' => $reasonCode,
        ];
        if ($reason !== '') {
            $payload['reason'] = $reason;
        }

        try {
            $data = $this->apiClient->post(self::OPERATION, GhnEndpoints::CANCEL_ORDER, $payload);
        } catch (ProviderTimeoutException | ProviderRemoteException | ProviderServiceUnavailableException $uncertainException) {
            // r2 taxonomy: transport ambiguity (timeout / connection error) and provider 5xx both
            // leave the applied-vs-not-applied question open — never reconciled by guessing.
            // The request may have landed provider-side; result stays UNKNOWN until the E1
            // Order Info API answers. Never retried blind (mutation).
            $this->logger->warning('GHN cancel technical failure', [
                'order_code' => $orderCode,
                'reason' => $uncertainException->getMessage(),
            ]);

            return GhnActionOutcome::unknownResult(self::ACTION_CANCEL, $orderCode, $uncertainException->getMessage());
        } catch (ProviderRateLimitException $technicalException) {
            // 429 rejected the request BEFORE processing — definitively not applied; retryable
            // once the throttle window clears (still never retried automatically — mutation).
            $this->logger->warning('GHN cancel rate limited', [
                'order_code' => $orderCode,
                'reason' => $technicalException->getMessage(),
            ]);

            return GhnActionOutcome::technicalFailure(self::ACTION_CANCEL, $orderCode, 'TECHNICAL_ERROR', $technicalException->getMessage());
        } catch (
            ProviderAuthenticationException
            | ProviderInvalidAddressException
            | ProviderInvalidRequestException
            | ProviderRateUnavailableException $businessException
        ) {
            $this->logger->warning('GHN cancel unavailable', [
                'order_code' => $orderCode,
                'reason' => $businessException->getMessage(),
            ]);

            return GhnActionOutcome::businessRejected(
                self::ACTION_CANCEL,
                $orderCode,
                'PROVIDER_REJECTED',
                $businessException->getMessage()
            );
        }

        $perOrder = $data[0] ?? null;
        if (!is_array($perOrder) || !array_key_exists('result', $perOrder)) {
            // HTTP 200 with an unusable body — result uncertainty, never assumed success.
            $this->logger->warning('GHN cancel returned an unusable body.', ['order_code' => $orderCode]);

            return GhnActionOutcome::unknownResult(self::ACTION_CANCEL, $orderCode, 'Unusable cancellation response.');
        }

        if ((bool) ($perOrder['result'] ?? false) !== true) {
            $message = (string) ($perOrder['message'] ?? '');
            $this->logger->warning('GHN cancel refused by provider.', [
                'order_code' => $orderCode,
                'provider_message' => $message,
            ]);

            return GhnActionOutcome::businessRejected(self::ACTION_CANCEL, $orderCode, 'PROVIDER_REJECTED', $message);
        }

        $this->logger->call('GHN cancel accepted', ['order_code' => $orderCode]);

        return GhnActionOutcome::success(self::ACTION_CANCEL, $orderCode);
    }
}
