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
 * TASK-4ATBC4 (GHN-E2) — requests provider RETURN (force R2S) of an existing GHN order.
 *
 * Contract (matrix §10, docs 2026-09-11): POST `v2/switch-status/return` with `order_codes[]`
 * only (no reason field); PER-ORDER best-effort — a status outside
 * {delivery_fail, storing, waiting_to_return, return} ⇒ HTTP 200 with `result:false` + message.
 * HTTP status alone NEVER means success.
 *
 * Boundaries: this is the CARRIER return action only ("request provider to return the goods") —
 * Magento RMA, refunds, stock returns and customer communication are separate business domains.
 * Mutation: no automatic retry; uncertain results are surfaced as UNKNOWN_RESULT and reconciled
 * through the E1 Order-Info pipeline.
 *
 * TASK-GKHXY1 r2 uncertainty taxonomy for mutations: transport-level ambiguity (timeout,
 * connection error) and provider 5xx both leave applied-vs-not-applied open — the HTTP client
 * cannot prove a before-send failure, so the conservative fallback is UNKNOWN_RESULT. A 429
 * throttle is definitive not-applied evidence (rejected before processing) → TECHNICAL_FAILURE.
 * HTTP 200 with an unusable body is post-submission uncertainty → UNKNOWN_RESULT.
 */
class GhnReturnService
{
    private const OPERATION = 'return_order';

    public const ACTION_RETURN = 'return';

    public function __construct(
        private readonly GhnApiClientInterface $apiClient,
        private readonly GhnShipmentRepository $shipmentRepository,
        private readonly GhnLogger $logger
    ) {
    }

    public function requestReturn(Shipment $shipment): GhnActionOutcome
    {
        $shipmentId = (int) $shipment->getEntityId();
        $row = $this->shipmentRepository->findByShipmentId($shipmentId);

        if ($row === null
            || ($row['provider_status'] ?? '') !== GhnShipmentRepository::STATUS_SUBMITTED
            || empty($row['ghn_order_code'])
        ) {
            return GhnActionOutcome::businessRejected(
                self::ACTION_RETURN,
                (string) ($row['ghn_order_code'] ?? ''),
                'NO_PROVIDER_ORDER',
                'No SUBMITTED GHN order exists for this shipment.'
            );
        }

        $orderCode = (string) $row['ghn_order_code'];

        try {
            $data = $this->apiClient->post(self::OPERATION, GhnEndpoints::RETURN_ORDER, [
                'order_codes' => [$orderCode],
            ]);
        } catch (ProviderTimeoutException | ProviderRemoteException | ProviderServiceUnavailableException $uncertainException) {
            // r2 taxonomy: transport ambiguity (timeout / connection error) and provider 5xx both
            // leave the applied-vs-not-applied question open — result stays UNKNOWN until the E1
            // Order Info API answers. Never retried blind (mutation).
            $this->logger->warning('GHN return technical failure', [
                'order_code' => $orderCode,
                'reason' => $uncertainException->getMessage(),
            ]);

            return GhnActionOutcome::unknownResult(self::ACTION_RETURN, $orderCode, $uncertainException->getMessage());
        } catch (ProviderRateLimitException $technicalException) {
            // 429 rejected the request BEFORE processing — definitively not applied; retryable
            // once the throttle window clears (still never retried automatically — mutation).
            $this->logger->warning('GHN return rate limited', [
                'order_code' => $orderCode,
                'reason' => $technicalException->getMessage(),
            ]);

            return GhnActionOutcome::technicalFailure(self::ACTION_RETURN, $orderCode, 'TECHNICAL_ERROR', $technicalException->getMessage());
        } catch (
            ProviderAuthenticationException
            | ProviderInvalidAddressException
            | ProviderInvalidRequestException
            | ProviderRateUnavailableException $businessException
        ) {
            $this->logger->warning('GHN return unavailable', [
                'order_code' => $orderCode,
                'reason' => $businessException->getMessage(),
            ]);

            return GhnActionOutcome::businessRejected(
                self::ACTION_RETURN,
                $orderCode,
                'PROVIDER_REJECTED',
                $businessException->getMessage()
            );
        }

        $perOrder = $data[0] ?? null;
        if (!is_array($perOrder) || !array_key_exists('result', $perOrder)) {
            $this->logger->warning('GHN return returned an unusable body.', ['order_code' => $orderCode]);

            return GhnActionOutcome::unknownResult(self::ACTION_RETURN, $orderCode, 'Unusable return response.');
        }

        if ((bool) ($perOrder['result'] ?? false) !== true) {
            $message = (string) ($perOrder['message'] ?? '');
            $this->logger->warning('GHN return refused by provider.', [
                'order_code' => $orderCode,
                'provider_message' => $message,
            ]);

            return GhnActionOutcome::businessRejected(self::ACTION_RETURN, $orderCode, 'PROVIDER_REJECTED', $message);
        }

        $this->logger->call('GHN return accepted', ['order_code' => $orderCode]);

        return GhnActionOutcome::success(self::ACTION_RETURN, $orderCode);
    }
}
